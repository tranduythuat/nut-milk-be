<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsProductionTestData;
use Tests\TestCase;

class OrderCancellationReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProductionTestData;

    public function test_cancel_order_shrinks_draft_plan_without_touching_stock(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);

        $date = now()->addDays(2)->toDateString();
        $orderA = $this->createConfirmedOrder($variant, 10, $date);
        $orderB = $this->createConfirmedOrder($variant, 5, $date);

        $this->postJson('/api/v1/production-plans/generate', ['production_date' => $date])
            ->assertOk();

        $this->assertDatabaseHas('production_plan_items', [
            'product_variant_id' => $variant->id,
            'planned_quantity' => 15,
        ]);
        $this->assertDatabaseHas('production_plan_raw_material_requirements', [
            'raw_material_id' => $rawMaterial->id,
            'required_quantity' => 3.75, // 15 * 0.25
        ]);

        // Hủy đơn B (5 cái) trong lúc plan còn DRAFT
        $cancel = $this->postJson("/api/v1/orders/{$orderB->id}/cancel");
        $cancel->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('production_plan_items', [
            'product_variant_id' => $variant->id,
            'planned_quantity' => 10, // 15 - 5
        ]);
        $this->assertDatabaseHas('production_plan_raw_material_requirements', [
            'raw_material_id' => $rawMaterial->id,
            'required_quantity' => 2.5, // 10 * 0.25
        ]);

        // DRAFT chưa từng reserve trên raw_materials -> reserved_quantity vẫn = 0
        $rawMaterial->refresh();
        $this->assertEquals(0, $rawMaterial->reserved_quantity);
        $this->assertEquals(1000, $rawMaterial->stock);
    }

    public function test_cancel_order_releases_reserved_raw_material_when_plan_is_planned(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);

        $date = now()->addDays(2)->toDateString();
        $orderA = $this->createConfirmedOrder($variant, 10, $date);
        $orderB = $this->createConfirmedOrder($variant, 5, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', ['production_date' => $date])
            ->json('data');

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'planned'])
            ->assertOk();

        $rawMaterial->refresh();
        $this->assertEquals(3.75, $rawMaterial->reserved_quantity); // đã giữ chỗ cho cả 15

        // Hủy đơn B
        $this->postJson("/api/v1/orders/{$orderB->id}/cancel")->assertOk();

        $rawMaterial->refresh();
        $this->assertEquals(1000, $rawMaterial->stock); // chưa sản xuất gì -> stock không đổi
        $this->assertEquals(2.5, $rawMaterial->reserved_quantity); // 3.75 - 1.25 (5*0.25) được giải phóng

        $this->assertDatabaseHas('production_plan_items', [
            'product_variant_id' => $variant->id,
            'planned_quantity' => 10,
        ]);
    }

    public function test_cancel_order_never_reclaims_already_consumed_material(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);

        $date = now()->addDays(2)->toDateString();
        $orderA = $this->createConfirmedOrder($variant, 10, $date);
        $orderB = $this->createConfirmedOrder($variant, 5, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', ['production_date' => $date])
            ->json('data');

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'planned'])->assertOk();
        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'in_progress'])->assertOk();

        $itemId = $plan['items'][0]['id'];

        // Đã sản xuất xong TOÀN BỘ 15 cái trước khi ai đó kịp hủy đơn B
        $this->patchJson(
            "/api/v1/production-plans/{$plan['id']}/items/{$itemId}/quantity",
            ['produced_quantity' => 15]
        )->assertOk();

        $rawMaterial->refresh();
        $this->assertEquals(1000 - 3.75, $rawMaterial->stock);
        $this->assertEquals(0, $rawMaterial->reserved_quantity);

        $variant->refresh();
        $this->assertEquals(15, $variant->stock);

        // Giờ mới hủy đơn B — không còn gì để "giảm" vì đã sản xuất hết
        $this->postJson("/api/v1/orders/{$orderB->id}/cancel")->assertOk();

        $rawMaterial->refresh();
        // Nguyên liệu KHÔNG được hoàn lại
        $this->assertEquals(1000 - 3.75, $rawMaterial->stock);

        // planned_quantity KHÔNG giảm xuống dưới produced_quantity (15)
        $this->assertDatabaseHas('production_plan_items', [
            'product_variant_id' => $variant->id,
            'planned_quantity' => 15,
            'produced_quantity' => 15,
        ]);

        // Thành phẩm dư (5 cái phần của order B) vẫn nằm nguyên trong kho
        $variant->refresh();
        $this->assertEquals(15, $variant->stock);
    }

    public function test_cancel_order_partial_reduction_when_production_partially_done(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);

        $date = now()->addDays(2)->toDateString();
        $orderA = $this->createConfirmedOrder($variant, 10, $date);
        $orderB = $this->createConfirmedOrder($variant, 5, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', ['production_date' => $date])
            ->json('data');

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'planned'])->assertOk();
        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'in_progress'])->assertOk();

        $itemId = $plan['items'][0]['id'];

        // Đã sản xuất 12/15 — nhiều hơn phần của order A (10), tức đã "lấn"
        // 2 đơn vị vào phần của order B trước khi order B bị hủy.
        $this->patchJson(
            "/api/v1/production-plans/{$plan['id']}/items/{$itemId}/quantity",
            ['produced_quantity' => 12]
        )->assertOk();

        $this->postJson("/api/v1/orders/{$orderB->id}/cancel")->assertOk();

        // Nhu cầu mới (loại order B) = 10. Đã sản xuất 12 > 10
        // -> maxReducible = planned(15) - produced(12) = 3
        // -> desiredReduction = 15 - 10 = 5, nhưng bị chặn ở reducible = 3
        // -> planned_quantity chỉ giảm còn 15 - 3 = 12 (bằng đúng produced_quantity)
        $this->assertDatabaseHas('production_plan_items', [
            'product_variant_id' => $variant->id,
            'planned_quantity' => 12,
            'produced_quantity' => 12,
        ]);
    }
}
