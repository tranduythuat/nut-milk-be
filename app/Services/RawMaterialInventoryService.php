<?php

namespace App\Services;

use App\Enums\RawMaterialMovementType;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class RawMaterialInventoryService
{
    public function __construct(
        protected UnitConversionService $units
    ) {}

    /** Nhập kho nguyên liệu. $unit = đơn vị người nhập dùng (vd 'kg'); null = mặc định base unit của material. */
    public function receive(
        RawMaterial $material,
        float $quantity,
        ?string $unit = null,
        ?Model $reference = null,
        ?string $note = null
    ): RawMaterial {
        if ($quantity <= 0) {
            throw new RuntimeException('Số lượng nhập kho không hợp lệ.');
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();

        $baseQuantity = $this->normalizeToMaterialUnit($material, $quantity, $unit);
        $before = (float) $material->stock;

        $material->increment('stock', $baseQuantity);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::RECEIVE,
            'quantity' => $baseQuantity,
            'stock_before' => $before,
            'stock_after' => $before + $baseQuantity,
            'note' => $this->noteWithUnitContext($note, $quantity, $unit, $baseQuantity, $material),
            ...$this->referenceColumns($reference),
        ]);

        return $material->refresh();
    }

    /** Điều chỉnh tồn kho về một con số cụ thể (kiểm kê thực tế). $newValue theo $unit đã cho. */
    public function adjustTo(
        RawMaterial $material,
        float $newValue,
        ?string $unit = null,
        ?string $note = null
    ): RawMaterial {
        if ($newValue < 0) {
            throw new RuntimeException('Tồn kho sau điều chỉnh không hợp lệ.');
        }

        $material = RawMaterial::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();

        $baseNewStock = $this->normalizeToMaterialUnit($material, $newValue, $unit);

        if ($baseNewStock < (float) $material->reserved_quantity) {
            throw new RuntimeException(sprintf(
                'Tồn kho sau kiểm kê (%s %s) thấp hơn lượng đang giữ chỗ cho kế hoạch sản xuất (%s %s). Hãy hủy hoặc điều chỉnh kế hoạch liên quan trước.',
                $baseNewStock,
                $material->unit,
                (float) $material->reserved_quantity,
                $material->unit
            ));
        }

        $before = (float) $material->stock;

        $material->update(['stock' => $baseNewStock]);

        $material->stockMovements()->create([
            'type' => RawMaterialMovementType::ADJUSTMENT,
            'quantity' => $baseNewStock - $before,
            'stock_before' => $before,
            'stock_after' => $baseNewStock,
            'note' => $this->noteWithUnitContext($note, $newValue, $unit, $baseNewStock, $material),
        ]);

        return $material->refresh();
    }

    /*
     |--------------------------------------------------------------------------
     | reserve() / release() / consume() / reverseConsumption() GIỮ NGUYÊN,
     | không cần tham số $unit — các hàm này luôn được gọi từ nội bộ hệ
     | thống (ProductionStatusService, ProductionQuantityService) với số
     | liệu ĐÃ ở base unit (tính từ ProductVariantIngredient.quantity, vốn
     | đã được quy đổi về base ngay lúc khai BOM). Không có input từ người
     | dùng chạm trực tiếp vào các hàm này.
     |--------------------------------------------------------------------------
     */

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

    /** Quy đổi $quantity từ $unit (nếu khác base) về đúng base unit của $material. */
    protected function normalizeToMaterialUnit(RawMaterial $material, float $quantity, ?string $unit): float
    {
        if ($unit === null || $unit === $material->unit) {
            return $quantity;
        }

        return $this->units->convert($quantity, $unit, $material->unit);
    }

    /** Ghi lại trong note phần quy đổi (nếu có) để audit không bị mất ngữ cảnh đơn vị gốc. */
    protected function noteWithUnitContext(
        ?string $note,
        float $inputQuantity,
        ?string $inputUnit,
        float $baseQuantity,
        RawMaterial $material
    ): ?string {
        if ($inputUnit === null || $inputUnit === $material->unit) {
            return $note;
        }

        $conversionNote = "(Nhập: {$inputQuantity} {$inputUnit} = {$baseQuantity} {$material->unit})";

        return $note ? "{$note} {$conversionNote}" : $conversionNote;
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
