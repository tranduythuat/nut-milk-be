<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\ProductVariantIngredient;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantIngredientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_sync_ingredients_for_variant(): void
    {
        $variant = ProductVariant::factory()->create();
        $walnutMilk = RawMaterial::factory()->create(['unit' => 'l']);
        $bottle = RawMaterial::factory()->create(['unit' => 'cái']);

        $response = $this->putJson("/api/v1/product-variants/{$variant->id}/ingredients", [
            'ingredients' => [
                ['raw_material_id' => $walnutMilk->id, 'quantity' => 0.25],
                ['raw_material_id' => $bottle->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        $this->assertDatabaseHas('product_variant_ingredients', [
            'product_variant_id' => $variant->id,
            'raw_material_id' => $walnutMilk->id,
            'quantity' => 0.25,
        ]);
    }

    public function test_sync_replaces_previous_recipe_entirely(): void
    {
        $variant = ProductVariant::factory()->create();
        $old = RawMaterial::factory()->create();
        $new = RawMaterial::factory()->create();

        ProductVariantIngredient::create([
            'product_variant_id' => $variant->id,
            'raw_material_id' => $old->id,
            'quantity' => 0.5,
        ]);

        $response = $this->putJson("/api/v1/product-variants/{$variant->id}/ingredients", [
            'ingredients' => [
                ['raw_material_id' => $new->id, 'quantity' => 0.3],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('product_variant_ingredients', [
            'raw_material_id' => $old->id,
        ]);
        $this->assertDatabaseHas('product_variant_ingredients', [
            'raw_material_id' => $new->id,
        ]);
    }

    public function test_rejects_duplicate_raw_material_in_same_payload(): void
    {
        $variant = ProductVariant::factory()->create();
        $material = RawMaterial::factory()->create();

        $response = $this->putJson("/api/v1/product-variants/{$variant->id}/ingredients", [
            'ingredients' => [
                ['raw_material_id' => $material->id, 'quantity' => 0.25],
                ['raw_material_id' => $material->id, 'quantity' => 0.30],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ingredients.0.raw_material_id']);
    }
}
