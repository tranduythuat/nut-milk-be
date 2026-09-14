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
        protected InventoryService $inventoryService
    ) {}

    public function cancel(
        Order $order,
        ?string $note = null
    ): Order {
        return DB::transaction(function () use (
            $order,
            $note
        ) {
            /*
             * Lock Order để tránh hai request
             * cùng cancel một Order.
             */
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            /*
             * Chỉ những trạng thái này được phép hủy.
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

            /*
             * Load toàn bộ dữ liệu cần cho việc hoàn inventory.
             */
            $order->load([
                'items.productVariant',
                'items.components.productVariant',
                'delivery',
            ]);

            /*
             * Hoàn inventory.
             */
            foreach ($order->items as $orderItem) {

                /*
                 * Product đơn.
                 */
                if ($orderItem->product_variant_id) {
                    $this->inventoryService->increase(
                        $orderItem->productVariant,
                        $orderItem->quantity
                    );

                    continue;
                }

                /*
                 * Combo.
                 *
                 * KHÔNG lấy combo_items hiện tại.
                 *
                 * Sử dụng snapshot:
                 * order_item_components
                 */
                foreach ($orderItem->components as $component) {
                    if (! $component->product_variant_id) {
                        continue;
                    }

                    $this->inventoryService->increase(
                        $component->productVariant,
                        $component->quantity * $orderItem->quantity
                    );
                }
            }

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
