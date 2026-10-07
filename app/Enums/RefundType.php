<?php

namespace App\Enums;

enum RefundType: string
{
    case COD_ADJUSTMENT = 'cod_adjustment';
    case CASH_REFUND = 'cash_refund';

    public function label(): string
    {
        return match ($this) {
            self::COD_ADJUSTMENT => 'Điều chỉnh giảm tiền thu COD',
            self::CASH_REFUND => 'Hoàn tiền (đã thu trước đó)',
        };
    }
}
