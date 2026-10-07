<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_login_and_get_token(): void
    {
        User::factory()->create([
            'email' => 'staff@test.com',
            'password' => bcrypt('secret123'),
            'role' => UserRole::STAFF,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@a.com', 'password' => bcrypt('right')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'a@a.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    public function test_staff_only_route_rejects_unauthenticated_request(): void
    {
        $response = $this->postJson('/api/v1/raw-materials', [
            'name' => 'X',
            'code' => 'X1',
            'unit' => 'g',
        ]);

        $response->assertStatus(401);
    }

    public function test_staff_only_route_rejects_customer_role(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        \Laravel\Sanctum\Sanctum::actingAs($customer, ['*']);

        $response = $this->postJson('/api/v1/raw-materials', [
            'name' => 'X',
            'code' => 'X1',
            'unit' => 'g',
        ]);

        $response->assertStatus(403);
    }

    public function test_staff_role_can_access_staff_only_route(): void
    {
        $staff = User::factory()->create(['role' => UserRole::STAFF]);
        \Laravel\Sanctum\Sanctum::actingAs($staff, ['*']);

        $response = $this->postJson('/api/v1/raw-materials', [
            'name' => 'Sữa nền',
            'code' => 'RM-AUTH1',
            'unit' => 'g',
        ]);

        $response->assertCreated();
    }

    public function test_public_routes_remain_accessible_without_auth(): void
    {
        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
    }
}
