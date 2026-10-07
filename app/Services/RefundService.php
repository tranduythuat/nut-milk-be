<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundService
{
    /**
     * Tạo khoản hoàn/điều chỉnh tương ứng với 1 đợt giao vừa bị hủy.
     *
     * Gọi hàm này SAU KHI delivery đã được set CANCELLED (không tự
     * kiểm tra lại trạng thái delivery ở đây — trách nhiệm đó thuộc
     * về OrderCancellationService, nơi gọi service này).
     */
    public function refundForCancelledDelivery(
        Delivery $delivery,
        ?string $note = null
    ): ?Refund {
        return DB::transaction(function () use ($delivery, $note) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($delivery->order_id);

            $amount = $delivery->item_subtotal + $delivery->delivery_fee;

            // Đợt giao không có giá trị tiền (hiếm, vd lỗi dữ liệu)
            // -> không tạo khoản hoàn rỗng vô nghĩa.
            if ($amount <= 0) {
                return null;
            }

            $isMoneyAlreadyCollected = $order->payment_status === PaymentStatus::PAID;

            $type = $isMoneyAlreadyCollected
                ? RefundType::CASH_REFUND
                : RefundType::COD_ADJUSTMENT;

            $status = $type === RefundType::COD_ADJUSTMENT
                ? RefundStatus::COMPLETED
                : RefundStatus::PENDING;

            $refund = Refund::create([
                'order_id' => $order->id,
                'delivery_id' => $delivery->id,
                'amount' => $amount,
                'type' => $type,
                'status' => $status,
                'note' => $note ?? $this->defaultNote($type, $delivery),
                'processed_at' => $status === RefundStatus::COMPLETED ? now() : null,
            ]);

            return $refund;
        });
    }

    /**
     * Staff xác nhận ĐÃ chuyển khoản hoàn tiền thật cho khách (chỉ
     * áp dụng cho Refund loại cash_refund, đang ở trạng thái pending).
     */
    public function completeCashRefund(Refund $refund, ?string $note = null): Refund
    {
        return DB::transaction(function () use ($refund, $note) {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($refund->type !== RefundType::CASH_REFUND) {
                throw new RuntimeException(
                    'Chỉ khoản hoàn loại cash_refund mới cần xác nhận xử lý thủ công.'
                );
            }

            if ($refund->status === RefundStatus::COMPLETED) {
                throw new RuntimeException('Khoản hoàn này đã được xử lý trước đó.');
            }

            $refund->update([
                'status' => RefundStatus::COMPLETED,
                'processed_at' => now(),
                'note' => $note ? "{$refund->note} | Xác nhận: {$note}" : $refund->note,
            ]);

            $this->finalizeOrderPaymentStatusIfFullyRefunded($refund->order_id);

            return $refund->fresh();
        });
    }

    /**
     * Nếu TOÀN BỘ đơn đã bị hủy (không còn delivery nào active) VÀ
     * mọi khoản cash_refund liên quan đã completed -> tự động đưa
     * order.payment_status về REFUNDED. Chỉ áp dụng khi payment_status
     * hiện tại là PAID (tránh ghi đè sai các trạng thái khác).
     */
    protected function finalizeOrderPaymentStatusIfFullyRefunded(int $orderId): void
    {
        $order = Order::query()->lockForUpdate()->findOrFail($orderId);

        if ($order->payment_status !== PaymentStatus::PAID) {
            return;
        }

        $order->loadMissing('deliveries');

        $hasActiveDelivery = $order->deliveries->contains(
            fn($d) => $d->status->value !== 'cancelled'
        );

        if ($hasActiveDelivery) {
            return;
        }

        $hasPendingCashRefund = $order->refunds()
            ->where('type', RefundType::CASH_REFUND->value)
            ->where('status', RefundStatus::PENDING->value)
            ->exists();

        if ($hasPendingCashRefund) {
            return;
        }

        $order->update(['payment_status' => PaymentStatus::REFUNDED]);

        $order->paymentStatusHistories()->create([
            'status' => PaymentStatus::REFUNDED,
            'note' => 'Toàn bộ đơn đã bị hủy và hoàn tất hoàn tiền.',
        ]);
    }

    protected function defaultNote(RefundType $type, Delivery $delivery): string
    {
        return $type === RefundType::COD_ADJUSTMENT
            ? "Giảm số tiền COD cần thu do hủy đợt giao #{$delivery->id} (chưa thu tiền trước đó)."
            : "Cần hoàn tiền mặt cho đợt giao #{$delivery->id} đã thu tiền trước đó.";
    }
}
