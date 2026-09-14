<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use RuntimeException;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function update(
        Order $order,
        OrderStatus $newStatus,
        ?string $note = null
    ): Order {
        return DB::transaction(function () use (
            $order,
            $newStatus,
            $note
        ) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $currentStatus = $order->status;

            if (! $this->canTransition(
                $currentStatus,
                $newStatus
            )) {
                throw new RuntimeException(
                    sprintf(
                        'Không thể chuyển đơn hàng từ "%s" sang "%s".',
                        $currentStatus->value,
                        $newStatus->value
                    )
                );
            }

            $order->update([
                'status' => $newStatus,
            ]);

            $order->statusHistories()->create([
                'status' => $newStatus,
                'note' => $note,
            ]);

            return $order->fresh([
                'items',
                'statusHistories',
            ]);
        });
    }

    protected function canTransition(
        OrderStatus $current,
        OrderStatus $new
    ): bool {
        $transitions = [
            OrderStatus::PENDING->value => [
                OrderStatus::CONFIRMED->value,
                OrderStatus::CANCELLED->value,
            ],

            OrderStatus::CONFIRMED->value => [
                OrderStatus::PROCESSING->value,
                OrderStatus::CANCELLED->value,
            ],

            OrderStatus::PROCESSING->value => [
                OrderStatus::SHIPPING->value,
                OrderStatus::CANCELLED->value,
            ],

            OrderStatus::SHIPPING->value => [
                OrderStatus::COMPLETED->value,
            ],

            OrderStatus::COMPLETED->value => [],

            OrderStatus::CANCELLED->value => [],
        ];

        return in_array(
            $new->value,
            $transitions[$current->value] ?? [],
            true
        );
    }
}
