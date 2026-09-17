<?php

namespace Tests\Feature;

use App\Enums\ProductionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsProductionTestData;
use Tests\TestCase;

class ProductionPlanFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProductionTestData;

    public function test_full_plan_lifecycle_consumes_raw_material_and_produces_stock(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);

        $deliveryDate = now()->addDays(2)->toDateString();
        $order = $this->createConfirmedOrder($variant, quantity: 10, deliveryDate: $deliveryDate);

        // 1. Generate plan
        $generate = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $deliveryDate,
        ]);

        $generate->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $planId = $generate->json('data.id');

        $item = $generate->json('data.items.0');
        $this->assertSame(10, $item['planned_quantity']);

        $requirement = $generate->json('data.raw_material_requirements.0');
        $this->assertSame(2.5, $requirement['required_quantity']); // 10 * 0.25

        // 2. Feasibility check — đủ nguyên liệu
        $feasibility = $this->getJson("/api/v1/production-plans/{$planId}/feasibility");
        $feasibility->assertOk()->assertJsonPath('data.feasible', true);

        // 3. DRAFT -> PLANNED: phải giữ chỗ nguyên liệu
        $planned = $this->patchJson("/api/v1/production-plans/{$planId}/status", [
            'status' => 'planned',
        ]);
        $planned->assertOk();

        $this->assertDatabaseHas('raw_materials', [
            'id' => $rawMaterial->id,
            'reserved_quantity' => 2.5,
        ]);

        // 4. PLANNED -> IN_PROGRESS
        $this->patchJson("/api/v1/production-plans/{$planId}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        // 5. Sản xuất một phần (6/10) -> tiêu hao nguyên liệu thật + cộng thành phẩm
        $itemId = $item['id'];

        $partial = $this->patchJson(
            "/api/v1/production-plans/{$planId}/items/{$itemId}/quantity",
            ['produced_quantity' => 6]
        );
        $partial->assertOk();

        $rawMaterial->refresh();
        $this->assertEquals(1000 - 1.5, $rawMaterial->stock);       // 6 * 0.25 = 1.5 tiêu hao
        $this->assertEquals(2.5 - 1.5, $rawMaterial->reserved_quantity); // phần còn giữ chỗ giảm tương ứng

        $variant->refresh();
        $this->assertEquals(6, $variant->stock);

        // 6. Complete khi chưa đủ sản lượng -> lỗi
        $incomplete = $this->postJson("/api/v1/production-plans/{$planId}/complete");
        $incomplete->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Chưa sản xuất đủ số lượng theo kế hoạch.');

        // 7. Sản xuất nốt phần còn lại
        $this->patchJson(
            "/api/v1/production-plans/{$planId}/items/{$itemId}/quantity",
            ['produced_quantity' => 10]
        )->assertOk();

        $rawMaterial->refresh();
        $this->assertEquals(1000 - 2.5, $rawMaterial->stock);
        $this->assertEquals(0, $rawMaterial->reserved_quantity);

        $variant->refresh();
        $this->assertEquals(10, $variant->stock);

        // 8. Complete thành công -> delivery của order chuyển sang preparing
        $complete = $this->postJson("/api/v1/production-plans/{$planId}/complete");
        $complete->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'status' => 'preparing',
        ]);
    }

    public function test_generate_fails_when_no_orders_for_date(): void
    {
        $response = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Không có đơn hàng cho ngày giao này.');
    }
}
