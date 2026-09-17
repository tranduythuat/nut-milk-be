<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderCancellationService
{
    public function __construct(
        protected ProductionPlanReconciliationService $productionPlanReconciliationService
    ) {}

    public function cancel(
        Order $order,
        ?string $note = null
    ): Order {
        return DB::transaction(function () use (
            $order,
            $note
        ) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            /*
             * Chỉ những trạng thái này được phép hủy.
             *
             * Đây đều là các trạng thái TRƯỚC "shipping" → hàng thành
             * phẩm chưa rời kho (stock chỉ bị trừ tại
             * OrderDeliveryWorkflowService::startShipping), nên KHÔNG
             * cần hoàn stock thành phẩm ở đây.
             */
            if (! in_array($order->status, [
                OrderStatus::PENDING,
                OrderStatus::CONFIRMED,
                OrderStatus::PROCESSING,
            ], true)) {
                throw new RuntimeException(
                    sprintf(
                        'Không thể hủy đơn hàng đang ở trạng thái "%s".',
                        $order->status->label()
                    )
                );
            }

            $order->load(['delivery']);

            /*
             * Hủy Delivery nếu Delivery vẫn chưa bắt đầu giao.
             */
            if ($order->delivery) {
                if (in_array($order->delivery->status, [
                    DeliveryStatus::PENDING,
                    DeliveryStatus::PREPARING,
                ], true)) {
                    $order->delivery->update([
                        'status' => DeliveryStatus::CANCELLED,
                    ]);

                    $order->delivery->statusHistories()->create([
                        'status' => DeliveryStatus::CANCELLED,
                        'note' => $note ??
                            'Delivery được hủy cùng đơn hàng.',
                    ]);
                } else {
                    throw new RuntimeException(
                        'Không thể hủy đơn hàng vì delivery đã bắt đầu giao.'
                    );
                }
            }

            /*
             * Thu nhỏ Production Plan tương ứng (nếu có), giải phóng
             * đúng phần nguyên liệu CHƯA sản xuất. Gọi TRƯỚC khi đổi
             * order->status để không ảnh hưởng logic — excludeOrderId
             * đã tự loại trừ đơn này khỏi phép tính rồi.
             */
            $this->productionPlanReconciliationService
                ->shrinkForCancelledOrder($order);

            /*
             * Update Order.
             */
            $order->update([
                'status' => OrderStatus::CANCELLED,
            ]);

            /*
             * Ghi Order Status History.
             */
            $order->statusHistories()->create([
                'status' => OrderStatus::CANCELLED,
                'note' => $note ?? 'Đơn hàng đã được hủy.',
            ]);

            return $order->fresh([
                'items.components.productVariant',
                'delivery.deliverySlot',
                'delivery.statusHistories',
                'statusHistories',
                'paymentStatusHistories',
            ]);
        });
    }
}
