<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderCompletionService
{
    public function checkAndComplete(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            if ($order->status === OrderStatus::COMPLETED) {
                return;
            }

            $order->loadMissing('deliveries');

            if ($order->deliveries->isEmpty()) {
                return;
            }

            // Mọi đợt giao (trừ các đợt đã CANCELLED) phải DELIVERED.
            $activeDeliveries = $order->deliveries->reject(
                fn($d) => $d->status === DeliveryStatus::CANCELLED
            );

            if ($activeDeliveries->isEmpty()) {
                return; // toàn bộ đợt đều bị hủy -> không tự complete
            }

            $allDelivered = $activeDeliveries->every(
                fn($d) => $d->status === DeliveryStatus::DELIVERED
            );

            if (! $allDelivered) {
                return;
            }

            $order->update(['status' => OrderStatus::COMPLETED, 'completed_at' => now()]);

            $order->statusHistories()->create([
                'status' => OrderStatus::COMPLETED,
                'note' => 'Tất cả đợt giao đã hoàn tất.',
            ]);
        });
    }
}
