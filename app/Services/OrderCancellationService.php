<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderCancellationService
{
    public function __construct(
        protected ProductionPlanReconciliationService $reconciliationService,
        protected RefundService $refundService,
    ) {}

    /**
     * Hủy CẢ ĐƠN — chỉ cho phép khi CHƯA đợt giao nào rời kho
     * (tất cả deliveries còn PENDING/PREPARING).
     */
    public function cancel(Order $order, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::CONFIRMED], true)) {
                throw new RuntimeException(sprintf(
                    'Không thể hủy đơn hàng đang ở trạng thái "%s".',
                    $order->status->label()
                ));
            }

            $order->load('deliveries');

            $hasShippedOrBeyond = $order->deliveries->contains(
                fn(Delivery $d) => ! in_array($d->status, [DeliveryStatus::PENDING, DeliveryStatus::PREPARING, DeliveryStatus::CANCELLED], true)
            );

            if ($hasShippedOrBeyond) {
                throw new RuntimeException(
                    'Không thể hủy cả đơn vì đã có đợt giao bắt đầu vận chuyển. Hãy hủy từng đợt chưa giao riêng lẻ.'
                );
            }

            foreach ($order->deliveries as $delivery) {
                if (in_array($delivery->status, [DeliveryStatus::PENDING, DeliveryStatus::PREPARING], true)) {
                    $this->cancelSingleDelivery($delivery, $note ?? 'Hủy cùng đơn hàng.');
                }
            }

            $order->update(['status' => OrderStatus::CANCELLED]);
            $order->statusHistories()->create([
                'status' => OrderStatus::CANCELLED,
                'note' => $note ?? 'Đơn hàng đã được hủy.',
            ]);

            return $order->fresh([
                'items.components.productVariant',
                'deliveries.deliverySlot',
                'deliveries.statusHistories',
                'statusHistories',
                'paymentStatusHistories',
            ]);
        });
    }

    /**
     * Hủy MỘT ĐỢT GIAO cụ thể, giữ nguyên các đợt còn lại — đây là
     * tính năng MỚI, phục vụ đúng kịch bản "khách không dùng hết, hủy
     * đợt 2, giữ đợt 1".
     */
    public function cancelDelivery(Delivery $delivery, ?string $note = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $note) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if (! in_array($delivery->status, [DeliveryStatus::PENDING, DeliveryStatus::PREPARING], true)) {
                throw new RuntimeException(sprintf(
                    'Không thể hủy đợt giao đang ở trạng thái "%s".',
                    $delivery->status->label()
                ));
            }

            $this->cancelSingleDelivery($delivery, $note ?? 'Khách yêu cầu hủy đợt giao này.');

            // Nếu đây là đợt cuối cùng còn active và đơn chỉ có đợt này
            // -> không tự hủy cả Order (khách có thể vẫn muốn giữ đơn
            // hoặc đã có đợt khác đã giao). Việc hủy Order là quyết định
            // tách biệt, qua endpoint cancel() riêng.

            return $delivery->fresh(['order', 'deliverySlot', 'statusHistories']);
        });
    }

    protected function cancelSingleDelivery(Delivery $delivery, string $note): void
    {
        $delivery->loadMissing('order');

        $delivery->update(['status' => DeliveryStatus::CANCELLED]);
        $delivery->statusHistories()->create(['status' => DeliveryStatus::CANCELLED, 'note' => $note]);

        // Thu nhỏ Production Plan của ngày riêng của đợt này.
        $this->reconciliationService->shrinkForCancelledDelivery($delivery);

        // Tạo khoản hoàn/điều chỉnh tương ứng với giá trị tiền của đợt này.
        $this->refundService->refundForCancelledDelivery($delivery, $note);
    }
}
