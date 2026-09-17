<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_raw_material(): void
    {
        $payload = [
            'name' => 'Sữa nền óc chó',
            'code' => 'RM-WALNUT-BASE',
            'unit' => 'l',
            'stock' => 50,
            'min_stock' => 5,
        ];

        $response = $this->postJson('/api/v1/raw-materials', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'RM-WALNUT-BASE')
            ->assertJsonPath('data.stock', 50)
            ->assertJsonPath('data.available_quantity', 50);

        $this->assertDatabaseHas('raw_materials', [
            'code' => 'RM-WALNUT-BASE',
        ]);
    }

    public function test_cannot_create_raw_material_with_duplicate_code(): void
    {
        RawMaterial::factory()->create(['code' => 'RM-DUP']);

        $response = $this->postJson('/api/v1/raw-materials', [
            'name' => 'Trùng mã',
            'code' => 'RM-DUP',
            'unit' => 'l',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_filter_low_stock_materials(): void
    {
        RawMaterial::factory()->create(['stock' => 5, 'min_stock' => 10]);   // low
        RawMaterial::factory()->create(['stock' => 50, 'min_stock' => 10]);  // ok

        $response = $this->getJson('/api/v1/raw-materials?low_stock=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_receive_stock_increases_stock_and_logs_movement(): void
    {
        $material = RawMaterial::factory()->create(['stock' => 100]);

        $response = $this->postJson("/api/v1/raw-materials/{$material->id}/receive", [
            'quantity' => 25.5,
            'note' => 'Nhập hàng từ nhà cung cấp A',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stock', 125.5);

        $this->assertDatabaseHas('raw_material_stock_movements', [
            'raw_material_id' => $material->id,
            'type' => 'receive',
        ]);
    }

    public function test_adjust_stock_sets_exact_value_and_logs_movement(): void
    {
        $material = RawMaterial::factory()->create(['stock' => 100]);

        $response = $this->postJson("/api/v1/raw-materials/{$material->id}/adjust", [
            'stock' => 80,
            'note' => 'Kiểm kê thực tế thiếu 20',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stock', 80);

        $this->assertDatabaseHas('raw_material_stock_movements', [
            'raw_material_id' => $material->id,
            'type' => 'adjustment',
            'quantity' => -20,
        ]);
    }

    public function test_available_quantity_excludes_reserved(): void
    {
        $material = RawMaterial::factory()->create([
            'stock' => 100,
            'reserved_quantity' => 30,
        ]);

        $response = $this->getJson("/api/v1/raw-materials/{$material->id}");

        $response->assertOk()
            ->assertJsonPath('data.available_quantity', 70);
    }
}
