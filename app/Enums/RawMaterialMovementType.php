<?php

namespace App\Enums;

enum RawMaterialMovementType: string
{
    case RECEIVE = 'receive';
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case CONSUME = 'consume';
    case REVERSE_CONSUME = 'reverse_consume';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVE => 'Nhập kho',
            self::RESERVE => 'Giữ chỗ cho sản xuất',
            self::RELEASE => 'Hủy giữ chỗ',
            self::CONSUME => 'Tiêu hao khi sản xuất',
            self::REVERSE_CONSUME => 'Hoàn nguyên liệu (điều chỉnh giảm)',
            self::ADJUSTMENT => 'Kiểm kê / điều chỉnh',
        };
    }
}
