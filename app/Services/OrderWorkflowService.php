<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderWorkflowService
{
    public function startProcessing(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->status !== OrderStatus::CONFIRMED) {
                throw new RuntimeException(
                    'Chỉ đơn hàng đã xác nhận mới có thể chuyển sang đang chuẩn bị.'
                );
            }

            $order->update([
                'status' => OrderStatus::PROCESSING,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::PROCESSING,
                'note' => 'Đơn hàng bắt đầu được chuẩn bị.',
            ]);

            return $order->fresh([
                'items',
                'delivery',
                'statusHistories',
            ]);
        });
    }

    public function startShipping(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->status !== OrderStatus::PROCESSING) {
                throw new RuntimeException(
                    'Chỉ đơn hàng đang chuẩn bị mới có thể chuyển sang đang giao.'
                );
            }

            if (! $order->delivery) {
                throw new RuntimeException(
                    'Đơn hàng chưa có thông tin giao hàng.'
                );
            }

            if (
                $order->delivery->status !==
                DeliveryStatus::SHIPPING
            ) {
                throw new RuntimeException(
                    'Delivery phải ở trạng thái đang giao trước khi Order chuyển sang đang giao.'
                );
            }

            $order->update([
                'status' => OrderStatus::SHIPPING,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::SHIPPING,
                'note' => 'Đơn hàng đang được giao.',
            ]);

            return $order->fresh([
                'items',
                'delivery.deliverySlot',
                'statusHistories',
            ]);
        });
    }

    public function complete(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->status !== OrderStatus::SHIPPING) {
                throw new RuntimeException(
                    'Chỉ đơn hàng đang giao mới có thể hoàn thành.'
                );
            }

            if (! $order->delivery) {
                throw new RuntimeException(
                    'Đơn hàng chưa có thông tin giao hàng.'
                );
            }

            if (
                $order->delivery->status !==
                DeliveryStatus::DELIVERED
            ) {
                throw new RuntimeException(
                    'Delivery phải ở trạng thái đã giao trước khi Order hoàn thành.'
                );
            }

            $order->update([
                'status' => OrderStatus::COMPLETED,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::COMPLETED,
                'note' => 'Đơn hàng đã hoàn thành.',
            ]);

            return $order->fresh([
                'items',
                'delivery',
                'statusHistories',
            ]);
        });
    }
}
