<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsProductionTestData;
use Tests\TestCase;

class ProductionPlanControllerErrorTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProductionTestData;

    public function test_generate_returns_422_on_validation_error(): void
    {
        $response = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => 'not-a-date',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_update_status_with_invalid_transition_returns_422(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 5, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ])->json('data');

        // DRAFT không thể nhảy thẳng sang IN_PROGRESS
        $response = $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJson([
                'message' => 'Không thể chuyển kế hoạch sản xuất từ "draft" sang "in_progress".',
            ]);
    }

    public function test_update_status_accepts_valid_transition(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 5, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ])->json('data');

        $response = $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", [
            'status' => 'planned',
            'note' => 'Xác nhận kế hoạch',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'planned');
    }

    public function test_update_quantity_exceeding_planned_returns_422(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 5, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ])->json('data');

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'planned'])->assertOk();
        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'in_progress'])->assertOk();

        $itemId = $plan['items'][0]['id'];

        $response = $this->patchJson(
            "/api/v1/production-plans/{$plan['id']}/items/{$itemId}/quantity",
            ['produced_quantity' => 999]
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJson([
                'message' => 'Số lượng sản xuất (999) không được vượt quá số lượng kế hoạch (5).',
            ]);
    }

    public function test_feasibility_endpoint_reports_shortage(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 100);
        $rawMaterial->update(['stock' => 10, 'reserved_quantity' => 0]);

        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 5, $date); // cần 500, chỉ có 10

        $plan = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ])->json('data');

        $response = $this->getJson("/api/v1/production-plans/{$plan['id']}/feasibility");

        $response->assertOk()
            ->assertJsonPath('data.feasible', false)
            ->assertJsonPath('data.shortages.0.raw_material_id', $rawMaterial->id);
    }

    public function test_planned_status_fails_when_raw_material_insufficient(): void
    {
        [$rawMaterial, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 100);
        $rawMaterial->update(['stock' => 10, 'reserved_quantity' => 0]);

        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 5, $date); // cần 500, chỉ có 10

        $plan = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ])->json('data');

        // Việc generate() vẫn cho tạo DRAFT (chưa reserve), nhưng
        // chuyển sang PLANNED (bước thực sự giữ chỗ) phải bị chặn.
        $response = $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", [
            'status' => 'planned',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseHas('production_plans', [
            'id' => $plan['id'],
            'status' => 'draft', // vẫn giữ nguyên, không bị chuyển dở dang
        ]);
    }
}
