<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Combo;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateCart(?int $userId, ?string $sessionId): Cart
    {
        $query = Cart::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId)
                ->whereNull('user_id');
        }

        $cart = $query->first();

        if ($cart) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
        ]);
    }

    public function addProduct(
        Cart $cart,
        ProductVariant $variant,
        int $quantity
    ): void {
        if (!$variant->is_active) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Sản phẩm này hiện không khả dụng.',
            ]);
        }

        if ($variant->stock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng sản phẩm không đủ.',
            ]);
        }

        DB::transaction(function () use ($cart, $variant, $quantity) {
            $item = $cart->items()
                ->where('product_variant_id', $variant->id)
                ->whereNull('combo_id')
                ->first();

            if ($item) {
                $newQuantity = $item->quantity + $quantity;

                if ($variant->stock < $newQuantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Số lượng sản phẩm không đủ.',
                    ]);
                }

                $item->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $variant->price,
                ]);

                return;
            }

            $cart->items()->create([
                'product_variant_id' => $variant->id,
                'combo_id' => null,
                'quantity' => $quantity,
                'unit_price' => $variant->price,
            ]);
        });
    }

    public function addCombo(
        Cart $cart,
        Combo $combo,
        int $quantity
    ): void {
        if (!$combo->is_active) {
            throw ValidationException::withMessages([
                'combo_id' => 'Combo này hiện không khả dụng.',
            ]);
        }

        DB::transaction(function () use ($cart, $combo, $quantity) {
            $item = $cart->items()
                ->where('combo_id', $combo->id)
                ->whereNull('product_variant_id')
                ->first();

            if ($item) {
                $item->update([
                    'quantity' => $item->quantity + $quantity,
                    'unit_price' => $combo->price,
                ]);

                return;
            }

            $cart->items()->create([
                'product_variant_id' => null,
                'combo_id' => $combo->id,
                'quantity' => $quantity,
                'unit_price' => $combo->price,
            ]);
        });
    }

    public function updateItem(
        Cart $cart,
        int $itemId,
        int $quantity
    ): void {
        $item = $cart->items()
            ->with([
                'productVariant',
                'combo',
            ])
            ->findOrFail($itemId);

        if ($item->productVariant) {
            if (!$item->productVariant->is_active) {
                throw ValidationException::withMessages([
                    'quantity' => 'Sản phẩm này hiện không khả dụng.',
                ]);
            }

            if ($item->productVariant->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng sản phẩm không đủ.',
                ]);
            }

            $item->update([
                'quantity' => $quantity,
                'unit_price' => $item->productVariant->price,
            ]);

            return;
        }

        if ($item->combo) {
            $item->update([
                'quantity' => $quantity,
                'unit_price' => $item->combo->price,
            ]);
        }
    }

    public function removeItem(Cart $cart, int $itemId): void
    {
        $cart->items()
            ->whereKey($itemId)
            ->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
