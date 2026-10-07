<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\DeliverySlot;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutDeliverySplitLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function addToCartAndGetCartItem(string $sessionId, int $quantity): CartItem
    {
        $variant = ProductVariant::factory()->create([
            'price' => 25000,
            'stock' => 100,   // <-- thêm dòng này
        ]);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ], ['X-Cart-Session' => $sessionId])->assertOk();

        return CartItem::where('product_variant_id', $variant->id)->firstOrFail();
    }

    protected function checkoutPayload(string $sessionId, array $deliveries): array
    {
        return [
            'session' => $sessionId,
            'body' => [
                'customer_name' => 'Test User',
                'customer_phone' => '0900000000',
                'customer_address' => '123 ABC',
                'payment_method' => 'cod',
                'deliveries' => $deliveries,
            ],
        ];
    }

    public function test_checkout_within_default_limit_succeeds(): void
    {
        config(['checkout.max_deliveries_per_order' => 3]);

        $sessionId = 'sess-limit-ok';
        $cartItem = $this->addToCartAndGetCartItem($sessionId, 6);
        $slot = DeliverySlot::factory()->create();

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Test User',
            'customer_phone' => '0900000000',
            'customer_address' => '123 ABC',
            'payment_method' => 'cod',
            'deliveries' => [
                ['delivery_date' => now()->addDays(2)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
                ['delivery_date' => now()->addDays(4)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
                ['delivery_date' => now()->addDays(6)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
            ],
        ], ['X-Cart-Session' => $sessionId]);

        $response->assertCreated();
        $this->assertCount(3, $response->json('data.deliveries'));
    }

    public function test_checkout_exceeding_default_limit_is_rejected(): void
    {
        config(['checkout.max_deliveries_per_order' => 3]);

        $sessionId = 'sess-limit-exceed';
        $cartItem = $this->addToCartAndGetCartItem($sessionId, 8);
        $slot = DeliverySlot::factory()->create();

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Test User',
            'customer_phone' => '0900000000',
            'customer_address' => '123 ABC',
            'payment_method' => 'cod',
            'deliveries' => [
                ['delivery_date' => now()->addDays(2)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
                ['delivery_date' => now()->addDays(4)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
                ['delivery_date' => now()->addDays(6)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
                ['delivery_date' => now()->addDays(8)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 2]]],
            ],
        ], ['X-Cart-Session' => $sessionId]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deliveries'])
            ->assertJsonFragment(['deliveries' => ['Đơn hàng chỉ được chia tối đa 3 đợt giao.']]);
    }

    public function test_limit_is_configurable_via_env(): void
    {
        config(['checkout.max_deliveries_per_order' => 2]);

        $sessionId = 'sess-limit-custom';
        $cartItem = $this->addToCartAndGetCartItem($sessionId, 9);
        $slot = DeliverySlot::factory()->create();

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Test User',
            'customer_phone' => '0900000000',
            'customer_address' => '123 ABC',
            'payment_method' => 'cod',
            'deliveries' => [
                ['delivery_date' => now()->addDays(2)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 3]]],
                ['delivery_date' => now()->addDays(4)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 3]]],
                ['delivery_date' => now()->addDays(6)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 3]]],
            ],
        ], ['X-Cart-Session' => $sessionId]);

        // Giới hạn custom = 2, request có 3 đợt -> phải bị từ chối dù
        // trước đó test với limit=3 cùng số đợt lại hợp lệ.
        $response->assertStatus(422)->assertJsonValidationErrors(['deliveries']);
    }

    public function test_duplicate_date_and_slot_in_same_request_is_rejected(): void
    {
        $sessionId = 'sess-dup-slot';
        $cartItem = $this->addToCartAndGetCartItem($sessionId, 6);
        $slot = DeliverySlot::factory()->create();
        $sameDate = now()->addDays(2)->toDateString();

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Test User',
            'customer_phone' => '0900000000',
            'customer_address' => '123 ABC',
            'payment_method' => 'cod',
            'deliveries' => [
                ['delivery_date' => $sameDate, 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 3]]],
                ['delivery_date' => $sameDate, 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 3]]],
            ],
        ], ['X-Cart-Session' => $sessionId]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deliveries.1']);
    }

    public function test_single_delivery_checkout_still_works_as_before(): void
    {
        // Hành vi tương thích ngược: 1 đợt giao duy nhất (giống hành
        // vi checkout cũ trước khi hỗ trợ multi-delivery) vẫn hoạt
        // động bình thường, không bị ảnh hưởng bởi rule max mới.
        $sessionId = 'sess-single';
        $cartItem = $this->addToCartAndGetCartItem($sessionId, 5);
        $slot = DeliverySlot::factory()->create();

        $response = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Test User',
            'customer_phone' => '0900000000',
            'customer_address' => '123 ABC',
            'payment_method' => 'cod',
            'deliveries' => [
                ['delivery_date' => now()->addDays(2)->toDateString(), 'delivery_slot_id' => $slot->id, 'items' => [['cart_item_id' => $cartItem->id, 'quantity' => 5]]],
            ],
        ], ['X-Cart-Session' => $sessionId]);

        $response->assertCreated();
        $this->assertCount(1, $response->json('data.deliveries'));
    }
}
