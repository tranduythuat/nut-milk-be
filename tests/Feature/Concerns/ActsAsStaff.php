<?php

namespace Tests\Feature\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ActsAsStaff
{
    protected function actingAsStaff(): User
    {
        $staff = User::factory()->create(['role' => UserRole::STAFF]);

        Sanctum::actingAs($staff, ['*']);

        return $staff;
    }
}
