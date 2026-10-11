<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [UserRole::STAFF, UserRole::ADMIN], true);
    }

    /**
     * Cổng vào duy nhất quyết định ai được mở /admin.
     * Khách hàng (role=customer) dù có tài khoản vẫn bị chặn ở đây,
     * không chỉ dựa vào route middleware.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff();
    }
}
