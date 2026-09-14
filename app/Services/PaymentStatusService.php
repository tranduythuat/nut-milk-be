<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentStatusService
{
    public function update(
        Order $order,
        PaymentStatus $newStatus,
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

            $currentStatus = $order->payment_status;

            if (
                ! $this->canTransition(
                    $currentStatus,
                    $newStatus
                )
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Không thể chuyển trạng thái thanh toán từ "%s" sang "%s".',
                        $currentStatus->value,
                        $newStatus->value
                    )
                );
            }

            $order->update([
                'payment_status' => $newStatus,
            ]);

            $order->paymentStatusHistories()->create([
                'status' => $newStatus,
                'note' => $note,
            ]);

            return $order->fresh([
                'items',
                'statusHistories',
                'paymentStatusHistories',
            ]);
        });
    }

    protected function canTransition(
        PaymentStatus $from,
        PaymentStatus $to
    ): bool {
        return match ($from) {
            PaymentStatus::PENDING =>
            in_array($to, [
                PaymentStatus::PAID,
                PaymentStatus::FAILED,
            ], true),

            PaymentStatus::PAID =>
            $to === PaymentStatus::REFUNDED,

            PaymentStatus::FAILED,
            PaymentStatus::REFUNDED =>
            false,
        };
    }
}
