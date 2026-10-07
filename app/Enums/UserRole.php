<?php

namespace App\Enums;

enum UserRole: string
{
    case CUSTOMER = 'customer';
    case STAFF = 'staff';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Khách hàng',
            self::STAFF => 'Nhân viên',
            self::ADMIN => 'Quản trị viên',
        };
    }
}
