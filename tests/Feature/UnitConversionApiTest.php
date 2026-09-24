<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitConversionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_non_base_unit(): void
    {
        $response = $this->postJson('/api/v1/raw-materials', [
            'name' => 'Test',
            'code' => 'RM-X1',
            'unit' => 'kg', // không phải base unit
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['unit']);
    }

    public function test_store_with_stock_unit_converts_initial_stock(): void
    {
        $response = $this->postJson('/api/v1/raw-materials', [
            'name' => 'Bột sữa test',
            'code' => 'RM-X2',
            'unit' => 'g',
            'stock' => 2,
            'stock_unit' => 'kg',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.unit', 'g')
            ->assertJsonPath('data.stock', 2000);
    }

    public function test_receive_converts_input_unit_to_material_base_unit(): void
    {
        $material = RawMaterial::factory()->create(['unit' => 'ml', 'stock' => 1000]);

        $response = $this->postJson("/api/v1/raw-materials/{$material->id}/receive", [
            'quantity' => 2,
            'unit' => 'l',
            'note' => 'Nhập 2 lít',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stock', 3000); // 1000 + (2l = 2000ml)
    }

    public function test_receive_rejects_mismatched_unit_type(): void
    {
        $material = RawMaterial::factory()->create(['unit' => 'ml', 'stock' => 1000]);

        $response = $this->postJson("/api/v1/raw-materials/{$material->id}/receive", [
            'quantity' => 2,
            'unit' => 'kg', // sai type (mass vs volume)
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_adjust_converts_input_unit(): void
    {
        $material = RawMaterial::factory()->create(['unit' => 'g', 'stock' => 500]);

        $response = $this->postJson("/api/v1/raw-materials/{$material->id}/adjust", [
            'stock' => 1.2,
            'unit' => 'kg',
            'note' => 'Kiểm kê thực tế 1.2kg',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stock', 1200);
    }

    public function test_sync_ingredients_converts_quantity_to_material_base_unit(): void
    {
        $variant = ProductVariant::factory()->create();
        $material = RawMaterial::factory()->create(['unit' => 'ml']);

        $response = $this->putJson("/api/v1/product-variants/{$variant->id}/ingredients", [
            'ingredients' => [
                ['raw_material_id' => $material->id, 'quantity' => 0.25, 'unit' => 'l'],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('product_variant_ingredients', [
            'product_variant_id' => $variant->id,
            'raw_material_id' => $material->id,
            'quantity' => 250, // 0.25l -> 250ml, đúng base unit của material
        ]);
    }

    public function test_update_raw_material_cannot_change_unit(): void
    {
        $material = RawMaterial::factory()->create(['unit' => 'g']);

        $response = $this->patchJson("/api/v1/raw-materials/{$material->id}", [
            'unit' => 'kg', // field này không có trong rules -> bị bỏ qua
            'min_stock' => 5,
        ]);

        $response->assertOk();

        $material->refresh();
        $this->assertEquals('g', $material->unit); // KHÔNG đổi
        $this->assertEquals(5, $material->min_stock);
    }
}
