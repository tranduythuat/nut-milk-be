<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ProductVariant;
use RuntimeException;

class InventoryService
{
    public function decrease(
        ProductVariant $variant,
        int $quantity
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Số lượng không hợp lệ.'
            );
        }

        $variant = ProductVariant::query()
            ->whereKey($variant->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $variant->is_active) {
            throw new RuntimeException(
                "Sản phẩm {$variant->name} hiện không còn bán."
            );
        }

        if ($variant->stock < $quantity) {
            throw new RuntimeException(
                "Sản phẩm {$variant->id} không đủ tồn kho." .
                    "Còn lại: {$variant->stock}."
            );
        }

        $variant->decrement('stock', $quantity);
    }

    public function decreaseCombo(
        Combo $combo,
        int $quantity
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Số lượng combo không hợp lệ.'
            );
        }

        if (! $combo->is_active) {
            throw new RuntimeException(
                "Combo {$combo->name} hiện không còn bán."
            );
        }

        $combo->loadMissing('items.productVariant');

        if ($combo->items->isEmpty()) {
            throw new RuntimeException(
                "Combo {$combo->name} chưa có sản phẩm."
            );
        }

        foreach ($combo->items as $comboItem) {
            $requiredQuantity = $comboItem->quantity * $quantity;

            $this->decrease(
                $comboItem->productVariant,
                $requiredQuantity
            );
        }
    }

    /**
     * Increase stock của một ProductVariant.
     */
    public function increase(
        ProductVariant $variant,
        int $quantity
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Số lượng sản phẩm không hợp lệ.'
            );
        }

        $variant = ProductVariant::query()
            ->whereKey($variant->id)
            ->lockForUpdate()
            ->firstOrFail();

        $variant->increment('stock', $quantity);
    }

    /**
     * Increase stock của các component trong Combo.
     *
     * Lưu ý:
     * Method này dùng cho nghiệp vụ hoàn inventory
     * theo cấu hình Combo hiện tại.
     *
     * Cancellation của Order KHÔNG nên dùng method này.
     * Cancellation phải dùng OrderItemComponent snapshot.
     */
    public function increaseCombo(
        Combo $combo,
        int $quantity
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Số lượng combo không hợp lệ.'
            );
        }

        $combo->loadMissing([
            'items.productVariant',
        ]);

        if ($combo->items->isEmpty()) {
            throw new RuntimeException(
                "Combo {$combo->name} chưa có sản phẩm."
            );
        }

        foreach ($combo->items as $comboItem) {
            $requiredQuantity =
                $comboItem->quantity * $quantity;

            $this->increase(
                $comboItem->productVariant,
                $requiredQuantity
            );
        }
    }
}
