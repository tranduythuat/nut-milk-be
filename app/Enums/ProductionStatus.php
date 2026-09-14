<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case DRAFT = 'draft';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Bản nháp',
            self::PLANNED => 'Đã lập kế hoạch',
            self::IN_PROGRESS => 'Đang sản xuất',
            self::COMPLETED => 'Đã hoàn thành',
            self::CANCELLED => 'Đã hủy',
        };
    }
}
