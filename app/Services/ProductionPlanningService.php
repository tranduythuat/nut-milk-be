<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\ProductionPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionPlanningService
{
    public function __construct(
        protected RawMaterialRequirementService $rawMaterialRequirementService
    ) {}

    public function generate(string $productionDate): ProductionPlan
    {
        return DB::transaction(function () use ($productionDate) {
            $existingPlan = ProductionPlan::query()
                ->with('items')
                ->whereDate('production_date', $productionDate)
                ->lockForUpdate()
                ->first();

            if ($existingPlan && $existingPlan->status !== ProductionStatus::DRAFT) {
                throw new RuntimeException(sprintf(
                    'Kế hoạch sản xuất ngày %s đã ở trạng thái "%s" và không thể tạo lại.',
                    $productionDate,
                    $existingPlan->status->label()
                ));
            }

            $requirements = $this->calculateVariantDemand($productionDate);

            if ($requirements->isEmpty()) {
                throw new RuntimeException('Không có đơn hàng cho ngày giao này.');
            }

            $this->assertWithinCapacity($productionDate, $requirements);

            $plan = ProductionPlan::updateOrCreate(
                ['production_date' => $productionDate],
                ['status' => ProductionStatus::DRAFT]
            );

            $plan->items()->delete();

            foreach ($requirements as $variantId => $quantity) {
                $plan->items()->create([
                    'product_variant_id' => $variantId,
                    'planned_quantity' => $quantity,
                    'produced_quantity' => 0,
                ]);
            }

            $this->syncRawMaterialRequirements($plan, $requirements);

            return $plan->load([
                'items.productVariant.product',
                'rawMaterialRequirements.rawMaterial',
            ]);
        });
    }

    /**
     * Tính tổng nhu cầu thành phẩm (theo variant) của một ngày giao hàng,
     * dựa trên các đơn còn "sống" (CONFIRMED / PROCESSING).
     *
     * $excludeOrderId dùng khi cần tính lại nhu cầu SAU KHI loại bỏ
     * một đơn cụ thể — ví dụ khi đơn đó vừa bị hủy.
     *
     * @return Collection<int, int> [product_variant_id => quantity]
     */
    public function calculateVariantDemand(
        string $productionDate,
        ?int $excludeOrderId = null
    ): Collection {
        $orders = Order::query()
            ->with(['items.productVariant', 'items.components'])
            ->whereHas('delivery', fn($query) => $query->whereDate('delivery_date', $productionDate))
            ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::PROCESSING->value])
            ->when($excludeOrderId, fn($query) => $query->where('id', '!=', $excludeOrderId))
            ->get();

        return $this->calculateRequirements($orders);
    }

    protected function assertWithinCapacity(string $productionDate, Collection $requirements): void
    {
        $dailyCapacity = config('production.daily_capacity');

        if ($dailyCapacity === null) {
            return;
        }

        $totalUnits = $requirements->sum();

        if ($totalUnits > $dailyCapacity) {
            throw new RuntimeException(sprintf(
                'Nhu cầu sản xuất ngày %s (%d sản phẩm) vượt quá công suất tối đa (%d sản phẩm/ngày).',
                $productionDate,
                $totalUnits,
                $dailyCapacity
            ));
        }
    }

    protected function syncRawMaterialRequirements(ProductionPlan $plan, Collection $variantRequirements): void
    {
        $requirements = $this->rawMaterialRequirementService->calculate($variantRequirements);

        $plan->rawMaterialRequirements()->delete();

        foreach ($requirements as $rawMaterialId => $quantity) {
            $plan->rawMaterialRequirements()->create([
                'raw_material_id' => $rawMaterialId,
                'required_quantity' => $quantity,
            ]);
        }
    }

    protected function calculateRequirements(Collection $orders): Collection
    {
        $requirements = collect();

        foreach ($orders as $order) {
            foreach ($order->items as $orderItem) {
                if ($orderItem->product_variant_id) {
                    $this->addRequirement($requirements, $orderItem->product_variant_id, $orderItem->quantity);
                    continue;
                }

                foreach ($orderItem->components as $component) {
                    if ($component->product_variant_id) {
                        $this->addRequirement(
                            $requirements,
                            $component->product_variant_id,
                            $component->quantity * $orderItem->quantity
                        );
                    }
                }
            }
        }

        return $requirements;
    }

    protected function addRequirement(Collection $requirements, int $variantId, int $quantity): void
    {
        $requirements->put($variantId, ($requirements->get($variantId) ?? 0) + $quantity);
    }
}
