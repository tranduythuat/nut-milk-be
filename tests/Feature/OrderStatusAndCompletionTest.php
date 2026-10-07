<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ActsAsStaff;
use Tests\Feature\Concerns\BuildsProductionTestData;
use Tests\TestCase;

class OrderStatusAndCompletionTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsStaff;
    use BuildsProductionTestData;

    public function test_request_rejects_status_value_not_in_allowed_list(): void
    {
        $this->actingAsStaff();

        [, $variant] = $this->createVariantWithRecipe();
        $order = $this->createConfirmedOrder($variant, 3, now()->addDays(2)->toDateString());

        // 'pending' và 'completed' không nằm trong danh sách cho phép
        // của UpdateOrderStatusRequest -> 422 ngay ở tầng validation.
        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_service_rejects_confirmed_to_confirmed_as_noop_transition(): void
    {
        $this->actingAsStaff();

        [, $variant] = $this->createVariantWithRecipe();
        $order = $this->createConfirmedOrder($variant, 3, now()->addDays(2)->toDateString());

        // status hiện tại đã là 'confirmed', request value 'confirmed'
        // hợp lệ ở tầng validation (nằm trong Rule::in), nhưng service
        // phải từ chối vì CONFIRMED -> CONFIRMED không có trong bảng
        // transitions (chỉ cho CONFIRMED -> CANCELLED).
        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Không thể chuyển đơn hàng từ "confirmed" sang "confirmed".']);
    }

    public function test_production_completion_moves_all_deliveries_of_same_date_to_preparing(): void
    {
        $this->actingAsStaff();

        [, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);
        $date = now()->addDays(2)->toDateString();

        $orderA = $this->createConfirmedOrder($variant, 4, $date);
        $orderB = $this->createConfirmedOrder($variant, 6, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', ['production_date' => $date])
            ->json('data');

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'planned'])->assertOk();
        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'in_progress'])->assertOk();

        $itemId = $plan['items'][0]['id'];
        $this->patchJson(
            "/api/v1/production-plans/{$plan['id']}/items/{$itemId}/quantity",
            ['produced_quantity' => 10]
        )->assertOk();

        $this->postJson("/api/v1/production-plans/{$plan['id']}/complete")->assertOk();

        // Cả 2 delivery (của 2 order khác nhau, cùng ngày) đều chuyển preparing.
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $orderA->id,
            'status' => 'preparing',
        ]);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $orderB->id,
            'status' => 'preparing',
        ]);
    }

    public function test_production_completion_skips_delivery_of_cancelled_order(): void
    {
        $this->actingAsStaff();

        [, $variant] = $this->createVariantWithRecipe(qtyPerUnit: 0.25);
        $date = now()->addDays(2)->toDateString();

        $orderA = $this->createConfirmedOrder($variant, 4, $date);
        $orderB = $this->createConfirmedOrder($variant, 6, $date);

        $plan = $this->postJson('/api/v1/production-plans/generate', ['production_date' => $date])
            ->json('data');

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'planned'])->assertOk();

        // Hủy order B SAU khi plan đã planned (reconciliation co lại
        // planned_quantity, nhưng ta chỉ cần xác nhận hành vi completion
        // ở đây: delivery của order B đã CANCELLED, không được chuyển
        // sang preparing khi complete).
        $this->postJson("/api/v1/orders/{$orderB->id}/cancel")->assertOk();

        $this->patchJson("/api/v1/production-plans/{$plan['id']}/status", ['status' => 'in_progress'])->assertOk();

        $freshPlan = $this->getJson("/api/v1/production-plans/{$plan['id']}")->json('data');
        $itemId = $freshPlan['items'][0]['id'];
        $remainingPlanned = $freshPlan['items'][0]['planned_quantity']; // chỉ còn phần order A

        $this->patchJson(
            "/api/v1/production-plans/{$plan['id']}/items/{$itemId}/quantity",
            ['produced_quantity' => $remainingPlanned]
        )->assertOk();

        $this->postJson("/api/v1/production-plans/{$plan['id']}/complete")->assertOk();

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $orderA->id,
            'status' => 'preparing',
        ]);

        // Delivery của order B VẪN LÀ cancelled, không bị completion đè lại.
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $orderB->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_set_order_status_to_completed_directly_even_if_in_allowed_enum(): void
    {
        $this->actingAsStaff();

        [, $variant] = $this->createVariantWithRecipe();
        $order = $this->createConfirmedOrder($variant, 3, now()->addDays(2)->toDateString());

        // Enum OrderStatus::COMPLETED tồn tại hợp lệ về mặt kiểu dữ
        // liệu, nhưng Rule::in ở request đã chặn trước khi chạm service.
        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed', // không đổi
        ]);
    }
}
