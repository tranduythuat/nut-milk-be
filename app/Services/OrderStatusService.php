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

    /**
     * Bảng chuyển trạng thái HỢP LỆ CHO THAO TÁC THỦ CÔNG qua
     * endpoint PATCH /orders/{order}/status.
     *
     * CỐ Ý KHÔNG liệt kê COMPLETED làm target ở bất kỳ nguồn nào:
     * COMPLETED chỉ được set bởi OrderCompletionService::checkAndComplete(),
     * tự động khi mọi Delivery (chưa bị hủy) của đơn đã ở trạng thái
     * DELIVERED. Nếu endpoint này cho phép set thẳng COMPLETED, đơn có
     * thể bị đánh dấu hoàn thành trong khi vẫn còn đợt giao chưa rời
     * kho — sai lệch nghiêm trọng giữa trạng thái hiển thị và thực tế.
     *
     * Từ khi Order không còn chứa khái niệm "processing"/"shipping"
     * (những trạng thái đó giờ sống hoàn toàn ở Delivery.status, vì
     * 1 đơn có thể có nhiều đợt giao với trạng thái khác nhau cùng
     * lúc), bảng transition chỉ còn 2 nguồn có thể chuyển tiếp:
     * PENDING và CONFIRMED.
     */
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
                OrderStatus::CANCELLED->value,
            ],

            OrderStatus::CANCELLED->value => [],

            OrderStatus::COMPLETED->value => [],
        ];

        return in_array(
            $new->value,
            $transitions[$current->value] ?? [],
            true
        );
    }
}
