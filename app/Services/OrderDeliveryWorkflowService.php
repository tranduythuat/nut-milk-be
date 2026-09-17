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
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Delivery:
     * preparing → shipping
     *
     * Order:
     * processing → shipping
     *
     * Đây là thời điểm hàng THỰC SỰ rời kho, nên trừ
     * finished-goods stock (product_variants.stock) tại đây.
     */
    public function startShipping(
        Delivery $delivery,
        ?string $note = null
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $note
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($delivery->order_id);

            if ($delivery->status !== DeliveryStatus::PREPARING) {
                throw new RuntimeException(
                    'Chỉ delivery đang chuẩn bị mới có thể chuyển sang đang giao.'
                );
            }

            if ($order->status !== OrderStatus::PROCESSING) {
                throw new RuntimeException(
                    'Order phải ở trạng thái đang chuẩn bị trước khi bắt đầu giao.'
                );
            }

            /*
             * Trừ tồn kho thành phẩm tại thời điểm hàng rời kho.
             *
             * Lưu ý: dùng snapshot order_item_components cho combo,
             * KHÔNG dùng combo_items hiện tại (có thể đã bị đổi công thức).
             */
            $order->load([
                'items.productVariant',
                'items.components.productVariant',
            ]);

            foreach ($order->items as $orderItem) {
                if ($orderItem->product_variant_id) {
                    $this->inventoryService->decrease(
                        $orderItem->productVariant,
                        $orderItem->quantity
                    );

                    continue;
                }

                foreach ($orderItem->components as $component) {
                    if (! $component->product_variant_id) {
                        continue;
                    }

                    $this->inventoryService->decrease(
                        $component->productVariant,
                        $component->quantity * $orderItem->quantity
                    );
                }
            }

            $delivery->update([
                'status' => DeliveryStatus::SHIPPING,
                'shipped_at' => now(),
            ]);

            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::SHIPPING,
                'note' => $note ?? 'Đơn hàng bắt đầu được giao.',
            ]);

            $order->update([
                'status' => OrderStatus::SHIPPING,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::SHIPPING,
                'note' => $note ?? 'Đơn hàng đang được giao.',
            ]);

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
     *
     * Không đụng vào stock ở bước này — hàng đã rời kho từ
     * lúc startShipping rồi.
     */
    public function completeDelivery(
        Delivery $delivery,
        ?string $note = null
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $note
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($delivery->order_id);

            if ($delivery->status !== DeliveryStatus::SHIPPING) {
                throw new RuntimeException(
                    'Chỉ delivery đang giao mới có thể hoàn thành.'
                );
            }

            if ($order->status !== OrderStatus::SHIPPING) {
                throw new RuntimeException(
                    'Order phải ở trạng thái đang giao trước khi hoàn thành.'
                );
            }

            $delivery->update([
                'status' => DeliveryStatus::DELIVERED,
                'delivered_at' => now(),
            ]);

            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::DELIVERED,
                'note' => $note ?? 'Đơn hàng đã được giao.',
            ]);

            $order->update([
                'status' => OrderStatus::COMPLETED,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::COMPLETED,
                'note' => $note ?? 'Đơn hàng đã hoàn thành.',
            ]);

            return $delivery->fresh([
                'order',
                'deliverySlot',
                'statusHistories',
            ]);
        });
    }
}
