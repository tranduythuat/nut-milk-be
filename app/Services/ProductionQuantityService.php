<?php

namespace App\Services;

use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use App\Models\ProductionPlanRawMaterialRequirement;
use App\Models\ProductVariant;
use App\Models\ProductVariantIngredient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionQuantityService
{
    public function __construct(
        protected RawMaterialInventoryService $rawMaterialInventoryService
    ) {}

    public function update(
        ProductionPlan $plan,
        ProductionPlanItem $item,
        int $producedQuantity
    ): ProductionPlan {
        return DB::transaction(function () use ($plan, $item, $producedQuantity) {
            $plan = ProductionPlan::query()->lockForUpdate()->findOrFail($plan->id);

            $item = ProductionPlanItem::query()
                ->lockForUpdate()
                ->where('production_plan_id', $plan->id)
                ->findOrFail($item->id);

            if ($plan->status->value !== 'in_progress') {
                throw new RuntimeException('Chỉ có thể cập nhật số lượng khi kế hoạch đang sản xuất.');
            }

            if ($producedQuantity < 0) {
                throw new RuntimeException('Số lượng sản xuất không được nhỏ hơn 0.');
            }

            if ($producedQuantity > $item->planned_quantity) {
                throw new RuntimeException(sprintf(
                    'Số lượng sản xuất (%d) không được vượt quá số lượng kế hoạch (%d).',
                    $producedQuantity,
                    $item->planned_quantity
                ));
            }

            $delta = $producedQuantity - $item->produced_quantity;

            if ($delta !== 0) {
                $this->applyRawMaterialDelta($plan, $item, $delta);
                $this->applyFinishedGoodsDelta($item, $delta);
            }

            $item->update(['produced_quantity' => $producedQuantity]);

            return $plan->fresh([
                'items.productVariant.product',
                'rawMaterialRequirements.rawMaterial',
            ]);
        });
    }

    /**
     * delta > 0: đang sản xuất thêm → tiêu hao nguyên liệu thật.
     * delta < 0: điều chỉnh giảm → hoàn nguyên liệu lại.
     */
    protected function applyRawMaterialDelta(ProductionPlan $plan, ProductionPlanItem $item, int $delta): void
    {
        $ingredients = ProductVariantIngredient::query()
            ->where('product_variant_id', $item->product_variant_id)
            ->with('rawMaterial')
            ->get();

        foreach ($ingredients as $ingredient) {
            $quantity = (float) $ingredient->quantity * abs($delta);

            if ($quantity <= 0) {
                continue;
            }

            $requirement = ProductionPlanRawMaterialRequirement::query()
                ->where('production_plan_id', $plan->id)
                ->where('raw_material_id', $ingredient->raw_material_id)
                ->lockForUpdate()
                ->first();

            if ($delta > 0) {
                $this->rawMaterialInventoryService->consume(
                    $ingredient->rawMaterial,
                    $quantity,
                    $plan,
                    'Tiêu hao nguyên liệu khi sản xuất.'
                );

                $requirement?->increment('consumed_quantity', $quantity);
                $requirement?->decrement(
                    'reserved_quantity',
                    min($quantity, (float) $requirement->reserved_quantity)
                );
            } else {
                $this->rawMaterialInventoryService->reverseConsumption(
                    $ingredient->rawMaterial,
                    $quantity,
                    $plan,
                    'Hoàn nguyên liệu do điều chỉnh giảm sản lượng.'
                );

                $requirement?->decrement(
                    'consumed_quantity',
                    min($quantity, (float) $requirement->consumed_quantity)
                );
                $requirement?->increment('reserved_quantity', $quantity);
            }
        }
    }

    protected function applyFinishedGoodsDelta(ProductionPlanItem $item, int $delta): void
    {
        $variant = ProductVariant::query()
            ->whereKey($item->product_variant_id)
            ->lockForUpdate()
            ->first();

        if (! $variant) {
            return;
        }

        if ($delta > 0) {
            $variant->increment('stock', $delta);
        } else {
            $variant->decrement('stock', min(abs($delta), $variant->stock));
        }
    }

    public function isCompleted(ProductionPlan $plan): bool
    {
        $plan->loadMissing('items');

        if ($plan->items->isEmpty()) {
            return false;
        }

        return $plan->items->every(
            fn(ProductionPlanItem $item) => $item->produced_quantity >= $item->planned_quantity
        );
    }
}
