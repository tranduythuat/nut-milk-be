<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

        /*
     * COMPLETED KHÔNG được set qua PATCH /orders/{id}/status — chỉ
     * OrderCompletionService được phép set, khi TOÀN BỘ Delivery của
     * đơn đã DELIVERED. Giữ case này để hiển thị, nhưng
     * OrderStatusService::canTransition() không bao giờ liệt kê nó
     * là target hợp lệ cho thao tác thủ công.
     */
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xác nhận',
            self::CONFIRMED => 'Đã xác nhận',
            self::CANCELLED => 'Đã hủy',
            self::COMPLETED => 'Đã hoàn thành',
        };
    }
}
