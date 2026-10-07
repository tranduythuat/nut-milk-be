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
            'unit' => 'ml',
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
     * Tạo 1 order CONFIRMED với ĐÚNG 1 đợt giao (trường hợp đặc biệt
     * n=1 của multi-delivery) — tương đương hành vi "đơn giao 1 lần"
     * trước khi hỗ trợ chia nhiều đợt.
     */
    protected function createConfirmedOrder(
        ProductVariant $variant,
        int $quantity,
        string $deliveryDate,
        OrderStatus $status = OrderStatus::CONFIRMED
    ): Order {
        return $this->createMultiDeliveryOrder(
            $variant,
            [['date' => $deliveryDate, 'quantity' => $quantity]],
            $status
        );
    }

    /**
     * Tạo 1 Order có NHIỀU Delivery (nhiều đợt giao), cùng 1 variant
     * nhưng số lượng/ngày khác nhau cho từng đợt. Dùng để test
     * reconciliation, refund, giới hạn số đợt, v.v. theo đúng schema
     * multi-delivery hiện tại.
     *
     * @param array<int, array{date: string, quantity: int}> $deliverySpecs
     */
    protected function createMultiDeliveryOrder(
        ProductVariant $variant,
        array $deliverySpecs,
        OrderStatus $status = OrderStatus::CONFIRMED
    ): Order {
        $totalQuantity = array_sum(array_column($deliverySpecs, 'quantity'));

        $order = Order::factory()->create([
            'status' => $status,
            'subtotal' => $variant->price * $totalQuantity,
            'total' => $variant->price * $totalQuantity,
        ]);

        $orderItem = $order->items()->create([
            'product_variant_id' => $variant->id,
            'combo_id' => null,
            'item_name' => $variant->product?->name ?? 'Test product',
            'variant_name' => $variant->name,
            'attributes' => $variant->attributes,
            'quantity' => $totalQuantity,
            'unit_price' => $variant->price,
            'total_price' => $variant->price * $totalQuantity,
        ]);

        foreach ($deliverySpecs as $spec) {
            $slot = DeliverySlot::factory()->create();

            $delivery = $order->deliveries()->create([
                'delivery_slot_id' => $slot->id,
                'delivery_date' => $spec['date'],
                'status' => DeliveryStatus::PENDING,
                'address' => $order->customer_address,
                'recipient_name' => $order->customer_name,
                'recipient_phone' => $order->customer_phone,
                'item_subtotal' => $variant->price * $spec['quantity'],
                'delivery_fee' => 0,
            ]);

            $delivery->items()->create([
                'order_item_id' => $orderItem->id,
                'quantity' => $spec['quantity'],
            ]);
        }

        return $order->fresh(['items', 'deliveries.items']);
    }
}
