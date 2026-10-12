<?php

namespace App\Services;

use App\Enums\OrderStatus;
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

            if ($newStatus === PaymentStatus::REFUNDED) {
                throw new RuntimeException(
                    'Trạng thái "đã hoàn tiền" được cập nhật tự động khi xác nhận khoản hoàn.'
                );
            }

            if ($newStatus === PaymentStatus::PAID && $order->status === OrderStatus::CANCELLED) {
                throw new RuntimeException('Không thể ghi nhận thanh toán cho đơn đã hủy.');
            }

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
            PaymentStatus::PENDING => in_array($to, [PaymentStatus::PAID, PaymentStatus::FAILED], true),

            // Cho phép thử thanh toán lại sau khi thất bại.
            PaymentStatus::FAILED => $to === PaymentStatus::PENDING,

            // PAID -> REFUNDED chỉ đi qua RefundService (không set tay).
            PaymentStatus::PAID,
            PaymentStatus::REFUNDED => false,
        };
    }
}
