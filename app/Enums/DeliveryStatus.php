<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case SHIPPING = 'shipping';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ giao',
            self::PREPARING => 'Đang chuẩn bị',
            self::SHIPPING => 'Đang giao',
            self::DELIVERED => 'Đã giao',
            self::FAILED => 'Giao thất bại',
            self::CANCELLED => 'Đã hủy',
        };
    }
}
