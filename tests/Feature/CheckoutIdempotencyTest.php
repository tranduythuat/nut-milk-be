<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_checkout_with_same_idempotency_key_does_not_create_two_orders(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0, 'price' => 25000]);
        $sessionId = 'sess-idem-test';

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ], ['X-Cart-Session' => $sessionId])->assertOk();

        $slot = \App\Models\DeliverySlot::factory()->create();
        $payload = [
            'customer_name' => 'A',
            'customer_phone' => '0900000000',
            'customer_address' => 'B',
            'payment_method' => 'cod',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'delivery_slot_id' => $slot->id,
        ];
        $headers = ['X-Cart-Session' => $sessionId, 'Idempotency-Key' => 'test-key-123'];

        $first = $this->postJson('/api/v1/checkout', $payload, $headers);
        $first->assertCreated();
        $orderId = $first->json('data.id');

        $second = $this->postJson('/api/v1/checkout', $payload, $headers);
        $second->assertCreated();

        $this->assertEquals($orderId, $second->json('data.id'));
        $this->assertEquals(1, \App\Models\Order::count());
        $this->assertTrue($second->headers->has('Idempotency-Replayed'));
    }
}
