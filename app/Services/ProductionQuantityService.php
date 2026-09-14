<?php

namespace App\Services;

use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionQuantityService
{
    public function update(
        ProductionPlan $plan,
        ProductionPlanItem $item,
        int $producedQuantity
    ): ProductionPlan {
        return DB::transaction(function () use (
            $plan,
            $item,
            $producedQuantity
        ) {
            $plan = ProductionPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            $item = ProductionPlanItem::query()
                ->lockForUpdate()
                ->where('production_plan_id', $plan->id)
                ->findOrFail($item->id);

            if ($plan->status->value !== 'in_progress') {
                throw new RuntimeException(
                    'Chỉ có thể cập nhật số lượng khi kế hoạch đang sản xuất.'
                );
            }

            if ($producedQuantity < 0) {
                throw new RuntimeException(
                    'Số lượng sản xuất không được nhỏ hơn 0.'
                );
            }

            if ($producedQuantity > $item->planned_quantity) {
                throw new RuntimeException(
                    sprintf(
                        'Số lượng sản xuất (%d) không được vượt quá số lượng kế hoạch (%d).',
                        $producedQuantity,
                        $item->planned_quantity
                    )
                );
            }

            $item->update([
                'produced_quantity' => $producedQuantity,
            ]);

            return $plan->fresh([
                'items.productVariant.product',
            ]);
        });
    }

    public function isCompleted(ProductionPlan $plan): bool
    {
        $plan->loadMissing('items');

        if ($plan->items->isEmpty()) {
            return false;
        }

        return $plan->items->every(
            fn(ProductionPlanItem $item) =>
            $item->produced_quantity >= $item->planned_quantity
        );
    }
}
