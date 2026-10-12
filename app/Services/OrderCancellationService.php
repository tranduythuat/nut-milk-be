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
        protected OrderCompletionService $orderCompletionService,
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

    public function cancelDelivery(Delivery $delivery, ?string $note = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $note) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $order = Order::query()->lockForUpdate()->findOrFail($delivery->order_id);

            if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::CONFIRMED], true)) {
                throw new RuntimeException(sprintf(
                    'Không thể hủy đợt giao của đơn đang ở trạng thái "%s".',
                    $order->status->label()
                ));
            }

            if (! in_array($delivery->status, [DeliveryStatus::PENDING, DeliveryStatus::PREPARING], true)) {
                throw new RuntimeException(sprintf(
                    'Không thể hủy đợt giao đang ở trạng thái "%s".',
                    $delivery->status->label()
                ));
            }

            $this->cancelSingleDelivery($delivery, $note ?? 'Khách yêu cầu hủy đợt giao này.');
            $this->syncOrderAfterDeliveryCancelled($order);

            return $delivery->fresh(['order', 'deliverySlot', 'statusHistories']);
        });
    }

    protected function syncOrderAfterDeliveryCancelled(Order $order): void
    {
        $order->load('deliveries');

        $allCancelled = $order->deliveries->every(
            fn(Delivery $d) => $d->status === DeliveryStatus::CANCELLED
        );

        if ($allCancelled) {
            $order->update(['status' => OrderStatus::CANCELLED]);
            $order->statusHistories()->create([
                'status' => OrderStatus::CANCELLED,
                'note' => 'Tất cả đợt giao đã bị hủy.',
            ]);

            return;
        }

        // Các đợt còn lại đều đã DELIVERED -> đơn hoàn tất.
        $this->orderCompletionService->checkAndComplete($order->id);
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
