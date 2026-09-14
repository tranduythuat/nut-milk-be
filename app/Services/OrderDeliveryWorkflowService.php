<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderDeliveryWorkflowService
{
    /**
     * Delivery:
     * preparing → shipping
     *
     * Order:
     * processing → shipping
     */
    public function startShipping(
        Delivery $delivery,
        ?string $note = null
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $note
        ) {
            /*
             * Lock Delivery trước.
             */
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            /*
             * Lock Order tương ứng.
             */
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($delivery->order_id);

            /*
             * Delivery phải đang preparing.
             */
            if ($delivery->status !== DeliveryStatus::PREPARING) {
                throw new RuntimeException(
                    'Chỉ delivery đang chuẩn bị mới có thể chuyển sang đang giao.'
                );
            }

            /*
             * Order phải đang processing.
             */
            if ($order->status !== OrderStatus::PROCESSING) {
                throw new RuntimeException(
                    'Order phải ở trạng thái đang chuẩn bị trước khi bắt đầu giao.'
                );
            }

            /*
             * Update Delivery.
             */
            $delivery->update([
                'status' => DeliveryStatus::SHIPPING,
                'shipped_at' => now(),
            ]);

            /*
             * Ghi lịch sử Delivery.
             */
            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::SHIPPING,
                'note' => $note ?? 'Đơn hàng bắt đầu được giao.',
            ]);

            /*
             * Update Order.
             */
            $order->update([
                'status' => OrderStatus::SHIPPING,
            ]);

            /*
             * Ghi lịch sử Order.
             */
            $order->statusHistories()->create([
                'status' => OrderStatus::SHIPPING,
                'note' => $note ?? 'Đơn hàng đang được giao.',
            ]);

            /*
             * Trả về Delivery mới nhất.
             */
            return $delivery->fresh([
                'order',
                'deliverySlot',
                'statusHistories',
            ]);
        });
    }

    /**
     * Delivery:
     * shipping → delivered
     *
     * Order:
     * shipping → completed
     */
    public function completeDelivery(
        Delivery $delivery,
        ?string $note = null
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $note
        ) {
            /*
             * Lock Delivery.
             */
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            /*
             * Lock Order.
             */
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($delivery->order_id);

            /*
             * Delivery phải đang shipping.
             */
            if ($delivery->status !== DeliveryStatus::SHIPPING) {
                throw new RuntimeException(
                    'Chỉ delivery đang giao mới có thể hoàn thành.'
                );
            }

            /*
             * Order phải đang shipping.
             */
            if ($order->status !== OrderStatus::SHIPPING) {
                throw new RuntimeException(
                    'Order phải ở trạng thái đang giao trước khi hoàn thành.'
                );
            }

            /*
             * Update Delivery.
             */
            $delivery->update([
                'status' => DeliveryStatus::DELIVERED,
                'delivered_at' => now(),
            ]);

            /*
             * Ghi lịch sử Delivery.
             */
            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::DELIVERED,
                'note' => $note ?? 'Đơn hàng đã được giao.',
            ]);

            /*
             * Update Order.
             */
            $order->update([
                'status' => OrderStatus::COMPLETED,
            ]);

            /*
             * Ghi lịch sử Order.
             */
            $order->statusHistories()->create([
                'status' => OrderStatus::COMPLETED,
                'note' => $note ?? 'Đơn hàng đã hoàn thành.',
            ]);

            /*
             * Trả về Delivery mới nhất.
             */
            return $delivery->fresh([
                'order',
                'deliverySlot',
                'statusHistories',
            ]);
        });
    }
}
