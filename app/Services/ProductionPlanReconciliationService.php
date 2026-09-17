<?php

namespace App\Services;

use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use App\Models\ProductionPlanRawMaterialRequirement;
use App\Models\ProductVariantIngredient;
use Illuminate\Support\Facades\DB;

class ProductionPlanReconciliationService
{
    public function __construct(
        protected ProductionPlanningService $productionPlanningService,
        protected RawMaterialInventoryService $rawMaterialInventoryService,
    ) {}

    /**
     * Thu nhỏ Production Plan tương ứng khi một Order bị hủy.
     *
     * NGUYÊN TẮC AN TOÀN (không thể vi phạm):
     * - Chỉ được giảm phần (planned_quantity - produced_quantity), tức
     *   phần CHƯA sản xuất.
     * - Nguyên liệu đã tiêu hao (consumed_quantity) để tạo ra thành phẩm
     *   thì KHÔNG BAO GIỜ được hoàn lại. Thành phẩm đó ở nguyên trong
     *   product_variants.stock — trở thành hàng tồn dùng chung, đơn
     *   khác trong tương lai vẫn lấy được bình thường.
     */
    public function shrinkForCancelledOrder(Order $order): void
    {
        $deliveryDate = $order->delivery?->delivery_date?->toDateString();

        if (! $deliveryDate) {
            return;
        }

        DB::transaction(function () use ($order, $deliveryDate) {
            $plan = ProductionPlan::query()
                ->whereDate('production_date', $deliveryDate)
                ->lockForUpdate()
                ->first();

            // Chưa từng generate plan cho ngày này, hoặc plan đã
            // COMPLETED/CANCELLED toàn bộ → không có gì để thu nhỏ.
            if (! $plan || in_array($plan->status, [
                ProductionStatus::COMPLETED,
                ProductionStatus::CANCELLED,
            ], true)) {
                return;
            }

            // Nhu cầu MỚI của cả ngày, sau khi loại trừ đơn vừa hủy.
            $newDemand = $this->productionPlanningService->calculateVariantDemand(
                $deliveryDate,
                excludeOrderId: $order->id
            );

            $items = ProductionPlanItem::query()
                ->where('production_plan_id', $plan->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_variant_id');

            $rawMaterialDeltas = collect();

            foreach ($items as $variantId => $item) {
                $oldPlanned = (int) $item->planned_quantity;
                $newPlanned = (int) ($newDemand->get($variantId) ?? 0);

                $desiredReduction = max(0, $oldPlanned - $newPlanned);

                if ($desiredReduction <= 0) {
                    continue;
                }

                // Trần an toàn: không bao giờ giảm xuống dưới mức đã sản xuất.
                $maxReducible = $oldPlanned - $item->produced_quantity;
                $reducible = min($desiredReduction, max(0, $maxReducible));

                if ($reducible <= 0) {
                    // Toàn bộ phần đơn này cần đã được sản xuất rồi
                    // → không giảm được gì, thành phẩm dư ở lại làm tồn kho.
                    continue;
                }

                $item->update([
                    'planned_quantity' => $oldPlanned - $reducible,
                ]);

                // Quy đổi phần được giảm sang nguyên liệu theo công thức (BOM).
                $ingredients = ProductVariantIngredient::query()
                    ->where('product_variant_id', $variantId)
                    ->get();

                foreach ($ingredients as $ingredient) {
                    $rawMaterialDeltas->put(
                        $ingredient->raw_material_id,
                        ($rawMaterialDeltas->get($ingredient->raw_material_id) ?? 0)
                            + ((float) $ingredient->quantity * $reducible)
                    );
                }
            }

            if ($rawMaterialDeltas->isEmpty()) {
                return;
            }

            $requirements = ProductionPlanRawMaterialRequirement::query()
                ->where('production_plan_id', $plan->id)
                ->whereIn('raw_material_id', $rawMaterialDeltas->keys())
                ->with('rawMaterial')
                ->lockForUpdate()
                ->get()
                ->keyBy('raw_material_id');

            foreach ($rawMaterialDeltas as $rawMaterialId => $delta) {
                $requirement = $requirements->get($rawMaterialId);

                if (! $requirement) {
                    continue;
                }

                // requirement->reserved_quantity luôn phản ánh đúng phần
                // "đã giữ chỗ nhưng chưa tiêu hao" (xem ProductionQuantityService:
                // mỗi lần consume() sẽ trừ reserved_quantity tương ứng).
                // Nên min(delta, reserved_quantity) chính là phần AN TOÀN
                // để trả lại kho.
                $releasable = min($delta, (float) $requirement->reserved_quantity);

                $requirement->update([
                    'required_quantity' => max(
                        0,
                        (float) $requirement->required_quantity - $delta
                    ),
                    'reserved_quantity' => max(
                        0,
                        (float) $requirement->reserved_quantity - $releasable
                    ),
                ]);

                // Plan còn ở DRAFT: chưa từng gọi reserve() thật trên
                // RawMaterial (reserve chỉ xảy ra khi DRAFT → PLANNED),
                // nên không có gì để release() ở kho — chỉ cần hạ
                // required_quantity ở trên là đủ.
                if ($releasable > 0 && $plan->status !== ProductionStatus::DRAFT) {
                    $this->rawMaterialInventoryService->release(
                        $requirement->rawMaterial,
                        $releasable,
                        $plan,
                        "Giải phóng nguyên liệu do đơn #{$order->order_number} bị hủy."
                    );
                }
            }
        });
    }
}
