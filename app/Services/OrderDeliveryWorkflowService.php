<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\OrderCompletionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderDeliveryWorkflowService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function startShipping(Delivery $delivery, ?string $note = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $note) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            $order = Order::query()->lockForUpdate()->findOrFail($delivery->order_id);

            if ($delivery->status !== DeliveryStatus::PREPARING) {
                throw new RuntimeException('Chỉ delivery đang chuẩn bị mới có thể chuyển sang đang giao.');
            }

            $delivery->load(['items.orderItem.productVariant', 'items.orderItem.components.productVariant']);

            foreach ($delivery->items as $deliveryItem) {
                $orderItem = $deliveryItem->orderItem;

                if ($orderItem->product_variant_id) {
                    $this->inventoryService->decrease($orderItem->productVariant, $deliveryItem->quantity);
                    continue;
                }

                foreach ($orderItem->components as $component) {
                    if (! $component->product_variant_id) {
                        continue;
                    }
                    // deliveryItem->quantity = số bộ combo trong đợt này.
                    $this->inventoryService->decrease(
                        $component->productVariant,
                        $component->quantity * $deliveryItem->quantity
                    );
                }
            }

            $delivery->update(['status' => DeliveryStatus::SHIPPING, 'shipped_at' => now()]);
            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::SHIPPING,
                'note' => $note ?? 'Đơn hàng đang được giao.',
            ]);

            // KHÔNG còn đồng bộ order->status ở đây — Order không có
            // trạng thái "shipping" nữa (xem OrderCompletionService).

            return $delivery->fresh(['order', 'deliverySlot', 'statusHistories', 'items.orderItem']);
        });
    }

    public function completeDelivery(Delivery $delivery, ?string $note = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $note) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if ($delivery->status !== DeliveryStatus::SHIPPING) {
                throw new RuntimeException('Chỉ delivery đang giao mới có thể hoàn thành.');
            }

            $delivery->update(['status' => DeliveryStatus::DELIVERED, 'delivered_at' => now()]);
            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::DELIVERED,
                'note' => $note ?? 'Đơn hàng đã được giao.',
            ]);

            // Sau khi 1 đợt giao xong, kiểm tra xem CẢ ĐƠN đã hoàn tất chưa.
            app(OrderCompletionService::class)->checkAndComplete($delivery->order_id);

            return $delivery->fresh(['order', 'deliverySlot', 'statusHistories']);
        });
    }
}
