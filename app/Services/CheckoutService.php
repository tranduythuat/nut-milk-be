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
        protected DeliveryAvailabilityService $deliveryAvailabilityService
    ) {}

    public function checkout(string $sessionId, array $data): Order
    {
        return DB::transaction(function () use ($sessionId, $data) {
            $cart = Cart::query()
                ->with(['items.productVariant.product', 'items.combo.items.productVariant.product'])
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new RuntimeException('Giỏ hàng đang trống.');
            }

            foreach ($cart->items as $item) {
                $this->validateCartItem($item);
            }

            $this->assertDeliveryAllocationsMatchCart($cart, $data['deliveries']);

            $cartItemsById = $cart->items->keyBy('id');

            // Tính TRƯỚC item_subtotal + delivery_fee của TỪNG đợt giao,
            // dựa trên đơn giá snapshot từ cart_items — làm cơ sở để:
            // (a) cộng dồn ra tổng của cả Order, (b) lưu cố định vào
            // từng Delivery để sau này hoàn tiền chính xác theo đợt.
            $deliveryGroups = $this->prepareDeliveryGroups($data['deliveries'], $cartItemsById);

            $subtotal = collect($deliveryGroups)->sum('item_subtotal');
            $deliveryFee = collect($deliveryGroups)->sum('delivery_fee');
            $discountAmount = 0;
            $total = $subtotal + $deliveryFee - $discountAmount;

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
                'payment_status' => PaymentStatus::PENDING->value,
                'payment_method' => $data['payment_method'] ?? null,
            ]);

            $orderItemByCartItemId = [];
            foreach ($cart->items as $cartItem) {
                $orderItemByCartItemId[$cartItem->id] = $this->createOrderItem($order, $cartItem);
            }

            foreach ($deliveryGroups as $group) {
                $this->createDeliveryGroup($order, $group, $orderItemByCartItemId);
            }

            $order->statusHistories()->create([
                'status' => OrderStatus::PENDING,
                'note' => 'Đơn hàng được tạo' . (count($deliveryGroups) > 1
                    ? sprintf(' (chia %d đợt giao).', count($deliveryGroups))
                    : '.'),
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
                'deliveries.deliverySlot',
                'deliveries.items.orderItem',
            ]);
        });
    }

    /**
     * Chuẩn bị dữ liệu từng đợt giao: tính item_subtotal (từ đơn giá
     * cart_item), delivery_fee (tính độc lập theo TỪNG đợt — chia
     * nhiều đợt hơn có thể tốn phí ship nhiều hơn).
     */
    protected function prepareDeliveryGroups(array $deliveries, $cartItemsById): array
    {
        $groups = [];

        foreach ($deliveries as $deliveryData) {
            $this->deliveryAvailabilityService->validateDeliverySelection(
                $deliveryData['delivery_date'],
                (int) $deliveryData['delivery_slot_id']
            );

            $units = 0;
            $itemSubtotal = 0;

            foreach ($deliveryData['items'] as $itemData) {
                $cartItem = $cartItemsById[$itemData['cart_item_id']];

                $perItem = $cartItem->product_variant_id
                    ? 1
                    : (int) $cartItem->combo->items->sum('quantity');

                $units += $perItem * $itemData['quantity'];
                $itemSubtotal += $cartItem->unit_price * $itemData['quantity'];
            }

            $this->deliveryAvailabilityService->assertCapacityAvailable(
                Carbon::createFromFormat('Y-m-d', $deliveryData['delivery_date'])->startOfDay(),
                $units
            );

            $groups[] = [
                'delivery_date' => $deliveryData['delivery_date'],
                'delivery_slot_id' => $deliveryData['delivery_slot_id'],
                'note' => $deliveryData['note'] ?? null,
                'items' => $deliveryData['items'],
                'item_subtotal' => $itemSubtotal,
                'delivery_fee' => $this->calculateDeliveryFee($itemSubtotal),
            ];
        }

        return $groups;
    }

    protected function assertDeliveryAllocationsMatchCart($cart, array $deliveries): void
    {
        $allocated = [];

        foreach ($deliveries as $deliveryData) {
            foreach ($deliveryData['items'] as $itemData) {
                $cartItemId = $itemData['cart_item_id'];
                $allocated[$cartItemId] = ($allocated[$cartItemId] ?? 0) + $itemData['quantity'];
            }
        }

        foreach ($cart->items as $cartItem) {
            $allocatedQty = $allocated[$cartItem->id] ?? 0;

            if ($allocatedQty !== (int) $cartItem->quantity) {
                throw new RuntimeException(sprintf(
                    'Phân bổ đợt giao không khớp: sản phẩm trong giỏ (id=%d) có số lượng %d nhưng chỉ được phân bổ %d qua các đợt giao.',
                    $cartItem->id,
                    $cartItem->quantity,
                    $allocatedQty
                ));
            }
        }

        $cartItemIds = $cart->items->pluck('id')->all();
        foreach (array_keys($allocated) as $cartItemId) {
            if (! in_array($cartItemId, $cartItemIds, true)) {
                throw new RuntimeException("cart_item_id {$cartItemId} không thuộc giỏ hàng hiện tại.");
            }
        }
    }

    protected function createDeliveryGroup(Order $order, array $group, array $orderItemByCartItemId): void
    {
        $deliveryDate = Carbon::createFromFormat('Y-m-d', $group['delivery_date'])->startOfDay();

        $slot = $this->deliveryAvailabilityService->reserveSlot(
            $deliveryDate,
            (int) $group['delivery_slot_id']
        );

        $delivery = $order->deliveries()->create([
            'delivery_slot_id' => $slot->id,
            'delivery_date' => $deliveryDate,
            'status' => DeliveryStatus::PENDING,
            'address' => $order->customer_address,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'note' => $group['note'],
            'item_subtotal' => $group['item_subtotal'],
            'delivery_fee' => $group['delivery_fee'],
        ]);

        $delivery->statusHistories()->create([
            'status' => DeliveryStatus::PENDING,
            'note' => 'Chờ giao hàng.',
        ]);

        foreach ($group['items'] as $itemData) {
            $orderItem = $orderItemByCartItemId[$itemData['cart_item_id']];

            $delivery->items()->create([
                'order_item_id' => $orderItem->id,
                'quantity' => $itemData['quantity'],
            ]);
        }
    }

    protected function validateCartItem($item): void
    {
        $hasVariant = ! is_null($item->product_variant_id);
        $hasCombo = ! is_null($item->combo_id);

        if ($hasVariant === $hasCombo) {
            throw new RuntimeException('Cart item phải có product variant hoặc combo.');
        }
        if ($item->quantity <= 0) {
            throw new RuntimeException('Số lượng sản phẩm không hợp lệ.');
        }
        if ($hasVariant && ! $item->productVariant) {
            throw new RuntimeException('Không tìm thấy product variant.');
        }
        if ($hasCombo && ! $item->combo) {
            throw new RuntimeException('Không tìm thấy combo.');
        }

        if ($hasVariant && ! $item->productVariant->is_active) {
            throw new RuntimeException("Sản phẩm \"{$item->productVariant->product->name} {$item->productVariant->name}\" hiện không còn bán.");
        }
        if ($hasCombo && ! $item->combo->is_active) {
            throw new RuntimeException("Combo \"{$item->combo->name}\" hiện không còn bán.");
        }
    }

    protected function createOrderItem(Order $order, $cartItem): OrderItem
    {
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
                'total_price' => $cartItem->unit_price * $cartItem->quantity,
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
            'total_price' => $cartItem->unit_price * $cartItem->quantity,
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

    protected function calculateDeliveryFee(int $itemSubtotal): int
    {
        return 0;
    }

    protected function generateOrderNumber(): string
    {
        return 'NM-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
    }
}
