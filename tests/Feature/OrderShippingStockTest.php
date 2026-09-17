<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsProductionTestData;
use Tests\TestCase;

class OrderShippingStockTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProductionTestData;

    public function test_stock_is_not_decreased_at_checkout_or_confirm(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $variant->update(['stock' => 20]);

        $order = $this->createConfirmedOrder($variant, 3, now()->addDays(2)->toDateString());

        $variant->refresh();
        $this->assertEquals(20, $variant->stock); // chưa hề bị trừ
    }

    public function test_stock_is_decreased_exactly_when_delivery_starts_shipping(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $variant->update(['stock' => 20]);

        $order = $this->createConfirmedOrder(
            $variant,
            3,
            now()->addDays(2)->toDateString(),
            status: OrderStatus::PROCESSING
        );

        $order->delivery()->update(['status' => DeliveryStatus::PREPARING]);

        $response = $this->patchJson("/api/v1/delivery/{$order->delivery->id}/status", [
            'status' => 'shipping',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'shipping');

        $variant->refresh();
        $this->assertEquals(17, $variant->stock); // 20 - 3

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'shipping',
        ]);
    }

    public function test_cancelling_processing_order_does_not_touch_finished_goods_stock(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $variant->update(['stock' => 20]);

        $order = $this->createConfirmedOrder(
            $variant,
            3,
            now()->addDays(2)->toDateString(),
            status: OrderStatus::PROCESSING
        );

        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

        $variant->refresh();
        $this->assertEquals(20, $variant->stock); // không cộng khống, không trừ gì
    }

    public function test_cannot_cancel_order_once_shipping_started(): void
    {
        [, $variant] = $this->createVariantWithRecipe();
        $variant->update(['stock' => 20]);

        $order = $this->createConfirmedOrder(
            $variant,
            3,
            now()->addDays(2)->toDateString(),
            status: OrderStatus::PROCESSING
        );
        $order->delivery()->update(['status' => DeliveryStatus::PREPARING]);

        $this->patchJson("/api/v1/delivery/{$order->delivery->id}/status", ['status' => 'shipping'])
            ->assertOk();

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
