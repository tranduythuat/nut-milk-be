<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\DeliveryStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Carbon\Carbon;

class CheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected DeliveryAvailabilityService $deliveryAvailabilityService
    ) {}

    public function checkout(
        string $sessionId,
        array $data
    ): Order {
        return DB::transaction(function () use (
            $sessionId,
            $data
        ) {
            $cart = Cart::query()
                ->with([
                    'items.productVariant.product',
                    'items.combo.items.productVariant.product',
                ])
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new RuntimeException(
                    'Giỏ hàng đang trống.'
                );
            }

            $deliveryDate = Carbon::createFromFormat(
                'Y-m-d',
                $data['delivery_date']
            )->startOfDay();

            $deliverySlot = $this->deliveryAvailabilityService
                ->reserveSlot(
                    $deliveryDate,
                    (int) $data['delivery_slot_id']
                );

            $subtotal = 0;

            foreach ($cart->items as $item) {
                $this->validateCartItem($item);

                $subtotal +=
                    $item->unit_price * $item->quantity;
            }

            $deliveryFee = $this->calculateDeliveryFee(
                $subtotal
            );

            $discountAmount = 0;

            $total =
                $subtotal
                + $deliveryFee
                - $discountAmount;


            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),

                'user_id' => $data['user_id'] ?? null,

                'customer_name' => $data['customer_name'],

                'customer_phone' => $data['customer_phone'],

                'customer_address' => $data['customer_address'],

                'note' => $data['note'] ?? null,

                'subtotal' => $subtotal,

                'delivery_fee' => $deliveryFee,

                'discount_amount' => $discountAmount,

                'total' => $total,

                'status' => OrderStatus::PENDING,

                'payment_status' => 'pending',

                'payment_method' => $data['payment_method'] ?? null,
            ]);

            $delivery = $order->delivery()->create([
                'delivery_slot_id' => $deliverySlot->id,
                'delivery_date' => $deliveryDate,
                'status' => DeliveryStatus::PENDING,
                'address' => $data['customer_address'],
                'recipient_name' => $data['customer_name'],
                'recipient_phone' => $data['customer_phone'],
                'note' => $data['note'] ?? null,
            ]);

            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::PENDING,
                'note' => 'Chờ giao hàng.',
            ]);

            foreach ($cart->items as $item) {
                $this->createOrderItem(
                    $order,
                    $item
                );
            }

            $order->statusHistories()->create([
                'status' => OrderStatus::PENDING,
                'note' => 'Đơn hàng được tạo.',
            ]);

            $order->paymentStatusHistories()->create([
                'status' => PaymentStatus::PENDING,
                'note' => 'Chờ thanh toán.',
            ]);

            $cart->items()->delete();

            return $order->load([
                'items',
                'statusHistories',
                'paymentStatusHistories',
                'delivery.deliverySlot',
            ]);
        });
    }

    protected function validateCartItem($item): void
    {
        $hasVariant = !is_null($item->product_variant_id);
        $hasCombo = !is_null($item->combo_id);

        if ($hasVariant === $hasCombo) {
            throw new RuntimeException(
                'Cart item phải có product variant hoặc combo.'
            );
        }

        if ($item->quantity <= 0) {
            throw new RuntimeException(
                'Số lượng sản phẩm không hợp lệ.'
            );
        }

        if ($hasVariant && ! $item->productVariant) {
            throw new RuntimeException(
                'Không tìm thấy product variant.'
            );
        }

        if ($hasCombo && ! $item->combo) {
            throw new RuntimeException(
                'Không tìm thấy combo.'
            );
        }
    }

    protected function createOrderItem(
        Order $order,
        $cartItem
    ): OrderItem {
        if ($cartItem->product_variant_id) {
            $variant = $cartItem->productVariant;

            return $order->items()->create([
                'product_variant_id' => $variant->id,
                'combo_id' => null,
                'item_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'attributes' => $variant->attributes,
                'quantity' => $cartItem->quantity,
                'unit_price' => $cartItem->unit_price,
                'total_price' =>
                $cartItem->unit_price * $cartItem->quantity,
            ]);
        }
        $combo = $cartItem->combo;
        $orderItem = $order->items()->create([
            'product_variant_id' => null,
            'combo_id' => $combo->id,
            'item_name' => $combo->name,
            'variant_name' => null,
            'attributes' => null,
            'quantity' => $cartItem->quantity,
            'unit_price' => $cartItem->unit_price,
            'total_price' =>
            $cartItem->unit_price * $cartItem->quantity,
        ]);

        foreach ($combo->items as $comboItem) {
            $variant = $comboItem->productVariant;

            $orderItem->components()->create([
                'product_variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'attributes' => $variant->attributes,
                'quantity' => $comboItem->quantity,
            ]);
        }
        return $orderItem;
    }

    protected function getItemName($item): string
    {
        if ($item->productVariant?->product) {
            return $item->productVariant->product->name;
        }

        if ($item->combo) {
            return $item->combo->name;
        }

        throw new RuntimeException(
            'Không thể xác định tên sản phẩm.'
        );
    }

    protected function getVariantName($item): ?string
    {
        return $item->productVariant?->name;
    }

    protected function getAttributes($item): ?array
    {
        return $item->productVariant?->attributes;
    }

    protected function calculateDeliveryFee(
        int $subtotal
    ): int {
        return 0;
    }

    protected function generateOrderNumber(): string
    {
        return 'NM-'
            . now()->format('YmdHis')
            . '-'
            . strtoupper(Str::random(4));
    }
}
