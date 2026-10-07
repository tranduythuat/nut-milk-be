<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Models\Delivery;
use App\Models\ProductionPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionCompletionService
{
    public function complete(
        ProductionPlan $plan,
        ?string $note = null
    ): ProductionPlan {
        return DB::transaction(function () use ($plan, $note) {

            $plan = ProductionPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            // 1. Phải đang in_progress
            if ($plan->status !== ProductionStatus::IN_PROGRESS) {
                throw new RuntimeException(
                    'Chỉ kế hoạch đang sản xuất mới có thể hoàn thành.'
                );
            }

            // 2. Kiểm tra đủ sản lượng
            $plan->load('items');

            if ($plan->items->isEmpty()) {
                throw new RuntimeException(
                    'Kế hoạch sản xuất chưa có sản phẩm.'
                );
            }

            $isCompleted = $plan->items->every(
                fn($item) =>
                $item->produced_quantity >=
                    $item->planned_quantity
            );

            if (! $isCompleted) {
                throw new RuntimeException(
                    'Chưa sản xuất đủ số lượng theo kế hoạch.'
                );
            }

            // 3. Hoàn thành Production Plan
            $plan->update([
                'status' => ProductionStatus::COMPLETED,
                'note' => $note ?? $plan->note,
            ]);

            /*
             * 4. Tìm các Delivery giao đúng NGÀY của plan này.
             *
             * ĐÃ ĐỔI so với bản cũ: trước đây query qua Order (1 order
             * = 1 delivery, order->status IN [CONFIRMED, PROCESSING]).
             * Giờ Order có thể có NHIỀU Delivery ở nhiều ngày khác
             * nhau, nên đơn vị đúng để gắn với "ngày sản xuất" là
             * Delivery, không phải Order.
             *
             * Điều kiện lọc:
             * - delivery_date đúng ngày sản xuất của plan.
             * - delivery.status = PENDING (chưa từng được đẩy sang
             *   preparing/shipping/... từ trước — tránh set lại đợt
             *   đã xử lý).
             * - order.status = CONFIRMED (loại các đơn đã bị hủy; từ
             *   khi OrderStatus không còn PROCESSING, CONFIRMED là
             *   trạng thái "đang hoạt động" duy nhất trước COMPLETED).
             *
             * Lưu ý: nếu 1 đợt giao đã bị hủy riêng lẻ (qua
             * OrderCancellationService::cancelDelivery), delivery.status
             * của nó đã là CANCELLED từ trước, nên tự động bị loại khỏi
             * điều kiện status = PENDING ở trên — không cần thêm filter
             * thủ công.
             */
            $deliveries = Delivery::query()
                ->whereDate('delivery_date', $plan->production_date)
                ->where('status', DeliveryStatus::PENDING->value)
                ->whereHas('order', function ($query) {
                    $query->where('status', OrderStatus::CONFIRMED->value);
                })
                ->get();

            // 5. Cho Delivery bắt đầu chuẩn bị
            foreach ($deliveries as $delivery) {
                $delivery->update([
                    'status' => DeliveryStatus::PREPARING,
                ]);

                $delivery->statusHistories()->create([
                    'status' => DeliveryStatus::PREPARING,
                    'note' => 'Sản xuất hoàn tất, bắt đầu chuẩn bị giao hàng.',
                ]);
            }

            return $plan->fresh([
                'items.productVariant.product',
            ]);
        });
    }
}
