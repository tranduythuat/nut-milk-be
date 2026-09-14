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
    public function generate(
        string $productionDate
    ): ProductionPlan {
        return DB::transaction(function () use (
            $productionDate
        ) {
            $existingPlan = ProductionPlan::query()
                ->with('items')
                ->whereDate('production_date', $productionDate)
                ->lockForUpdate()
                ->first();
            if ($existingPlan) {
                if ($existingPlan->status !== ProductionStatus::DRAFT) {
                    throw new RuntimeException(
                        sprintf(
                            'Kế hoạch sản xuất ngày %s đã ở trạng thái "%s" và không thể tạo lại.',
                            $productionDate,
                            $existingPlan->status->label()
                        )
                    );
                }
            }

            $orders = Order::query()
                ->with([
                    'items.productVariant',
                    'items.components',
                ])
                ->whereHas('delivery', function ($query) use (
                    $productionDate
                ) {
                    $query->whereDate(
                        'delivery_date',
                        $productionDate
                    );
                })
                ->whereIn('status', [
                    OrderStatus::CONFIRMED->value,
                    OrderStatus::PROCESSING->value,
                ])
                ->get();

            if ($orders->isEmpty()) {
                throw new RuntimeException(
                    'Không có đơn hàng cho ngày giao này.'
                );
            }

            $requirements = $this->calculateRequirements(
                $orders
            );

            $plan = ProductionPlan::updateOrCreate(
                [
                    'production_date' => $productionDate,
                ],
                [
                    'status' => ProductionStatus::DRAFT,
                ]
            );

            $plan->items()->delete();

            foreach ($requirements as $variantId => $quantity) {
                $plan->items()->create([
                    'product_variant_id' => $variantId,
                    'planned_quantity' => $quantity,
                    'produced_quantity' => 0,
                ]);
            }

            return $plan->load(
                'items.productVariant.product'
            );
        });
    }

    protected function calculateRequirements(
        Collection $orders
    ): Collection {
        $requirements = collect();

        foreach ($orders as $order) {
            foreach ($order->items as $orderItem) {

                if ($orderItem->product_variant_id) {
                    $this->addRequirement(
                        $requirements,
                        $orderItem->product_variant_id,
                        $orderItem->quantity
                    );

                    continue;
                }

                foreach ($orderItem->components as $component) {
                    $quantity = $component->quantity * $orderItem->quantity;

                    if ($component->product_variant_id) {
                        $this->addRequirement(
                            $requirements,
                            $component->product_variant_id,
                            $quantity
                        );
                    }
                }
            }
        }

        return $requirements;
    }

    protected function addRequirement(
        Collection $requirements,
        int $variantId,
        int $quantity
    ): void {
        $requirements->put(
            $variantId,
            ($requirements->get($variantId) ?? 0) + $quantity
        );
    }
}
