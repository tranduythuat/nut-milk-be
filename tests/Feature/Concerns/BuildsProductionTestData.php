<?php

namespace Tests\Feature\Concerns;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\DeliverySlot;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ProductVariantIngredient;
use App\Models\RawMaterial;

trait BuildsProductionTestData
{
    /**
     * Tạo 1 raw material + 1 product variant có công thức
     * (1 đơn vị variant cần $qtyPerUnit raw material).
     */
    protected function createVariantWithRecipe(float $qtyPerUnit = 0.25): array
    {
        $rawMaterial = RawMaterial::factory()->create([
            'unit' => 'l',
            'stock' => 1000,
            'reserved_quantity' => 0,
        ]);

        $variant = ProductVariant::factory()->create([
            'stock' => 0,
        ]);

        ProductVariantIngredient::create([
            'product_variant_id' => $variant->id,
            'raw_material_id' => $rawMaterial->id,
            'quantity' => $qtyPerUnit,
        ]);

        return [$rawMaterial, $variant];
    }

    /**
     * Tạo 1 order CONFIRMED, có 1 order_item cho $variant, có delivery
     * vào $deliveryDate (chưa gắn slot cụ thể, chỉ cần đúng ngày).
     */
    protected function createConfirmedOrder(
        ProductVariant $variant,
        int $quantity,
        string $deliveryDate,
        OrderStatus $status = OrderStatus::CONFIRMED
    ): Order {
        $slot = DeliverySlot::factory()->create();

        $order = Order::factory()->create([
            'status' => $status,
            'subtotal' => $variant->price * $quantity,
            'total' => $variant->price * $quantity,
        ]);

        $order->items()->create([
            'product_variant_id' => $variant->id,
            'combo_id' => null,
            'item_name' => $variant->product?->name ?? 'Test product',
            'variant_name' => $variant->name,
            'attributes' => $variant->attributes,
            'quantity' => $quantity,
            'unit_price' => $variant->price,
            'total_price' => $variant->price * $quantity,
        ]);

        $order->delivery()->create([
            'delivery_slot_id' => $slot->id,
            'delivery_date' => $deliveryDate,
            'status' => DeliveryStatus::PENDING,
            'address' => $order->customer_address,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
        ]);

        return $order->fresh(['items', 'delivery']);
    }
}
