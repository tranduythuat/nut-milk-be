<?php

namespace App\Services;

use App\Enums\RawMaterialMovementType;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class RawMaterialInventoryService
{
    /** Nhập kho nguyên liệu (mua hàng, nhập bổ sung...). */
    public function receive(
        RawMaterial $material,
        float $quantity,
        ?Model $reference = null,
        ?string $note = null
    ): RawMaterial {
        if ($quantity <= 0) {
            throw new RuntimeException('Số lượng nhập kho không hợp lệ.');
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
        $before = (float) $material->stock;

        $material->increment('stock', $quantity);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::RECEIVE,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $before + $quantity,
            'note' => $note,
            ...$this->referenceColumns($reference),
        ]);

        return $material->refresh();
    }

    /** Điều chỉnh tồn kho về một con số cụ thể (kiểm kê thực tế). */
    public function adjustTo(RawMaterial $material, float $newStock, ?string $note = null): RawMaterial
    {
        if ($newStock < 0) {
            throw new RuntimeException('Tồn kho sau điều chỉnh không hợp lệ.');
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
        $before = (float) $material->stock;

        $material->update(['stock' => $newStock]);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::ADJUSTMENT,
            'quantity' => $newStock - $before,
            'stock_before' => $before,
            'stock_after' => $newStock,
            'note' => $note,
        ]);

        return $material->refresh();
    }

    /** Giữ chỗ nguyên liệu cho một Production Plan (chưa trừ tồn kho thật). */
    public function reserve(
        RawMaterial $material,
        float $quantity,
        ?Model $reference = null,
        ?string $note = null
    ): RawMaterial {
        if ($quantity <= 0) {
            throw new RuntimeException('Số lượng giữ chỗ không hợp lệ.');
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();

        $available = (float) $material->stock - (float) $material->reserved_quantity;

        if ($available < $quantity) {
            throw new RuntimeException(sprintf(
                'Nguyên liệu "%s" không đủ để giữ chỗ. Cần: %s %s, khả dụng: %s %s.',
                $material->name,
                $quantity,
                $material->unit,
                $available,
                $material->unit
            ));
        }

        $material->increment('reserved_quantity', $quantity);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::RESERVE,
            'quantity' => $quantity,
            'note' => $note,
            ...$this->referenceColumns($reference),
        ]);

        return $material->refresh();
    }

    /** Hủy giữ chỗ (khi Production Plan bị hủy trước khi sản xuất). */
    public function release(
        RawMaterial $material,
        float $quantity,
        ?Model $reference = null,
        ?string $note = null
    ): RawMaterial {
        if ($quantity <= 0) {
            return $material;
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();

        $releaseQuantity = min($quantity, (float) $material->reserved_quantity);
        $material->decrement('reserved_quantity', $releaseQuantity);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::RELEASE,
            'quantity' => $releaseQuantity,
            'note' => $note,
            ...$this->referenceColumns($reference),
        ]);

        return $material->refresh();
    }

    /** Tiêu hao nguyên liệu THẬT khi sản xuất (trừ stock, đồng thời giải phóng phần đã giữ chỗ). */
    public function consume(
        RawMaterial $material,
        float $quantity,
        ?Model $reference = null,
        ?string $note = null
    ): RawMaterial {
        if ($quantity <= 0) {
            return $material;
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();

        if ((float) $material->stock < $quantity) {
            throw new RuntimeException(sprintf(
                'Nguyên liệu "%s" không đủ tồn kho thực tế. Cần: %s %s, còn: %s %s.',
                $material->name,
                $quantity,
                $material->unit,
                $material->stock,
                $material->unit
            ));
        }

        $before = (float) $material->stock;
        $material->decrement('stock', $quantity);

        $reservedRelease = min($quantity, (float) $material->reserved_quantity);
        if ($reservedRelease > 0) {
            $material->decrement('reserved_quantity', $reservedRelease);
        }

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::CONSUME,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $before - $quantity,
            'note' => $note,
            ...$this->referenceColumns($reference),
        ]);

        return $material->refresh();
    }

    /** Hoàn nguyên liệu khi điều chỉnh GIẢM sản lượng đã sản xuất. */
    public function reverseConsumption(
        RawMaterial $material,
        float $quantity,
        ?Model $reference = null,
        ?string $note = null
    ): RawMaterial {
        if ($quantity <= 0) {
            return $material;
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
        $before = (float) $material->stock;

        $material->increment('stock', $quantity);
        $material->increment('reserved_quantity', $quantity);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::REVERSE_CONSUME,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $before + $quantity,
            'note' => $note,
            ...$this->referenceColumns($reference),
        ]);

        return $material->refresh();
    }

    protected function referenceColumns(?Model $reference): array
    {
        if (! $reference) {
            return [];
        }

        return [
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
        ];
    }
}
