<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ProductionStatus;
use Illuminate\Support\Collection;

class ProductionPlan extends Model
{
    protected $fillable = [
        'production_date',
        'status',
        'note',
    ];

    protected $casts = [
        'production_date' => 'date',
        'status' => ProductionStatus::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(
            ProductionPlanItem::class
        );
    }

    public function rawMaterialRequirements(): HasMany
    {
        return $this->hasMany(ProductionPlanRawMaterialRequirement::class);
    }

    /** Các dòng nguyên liệu đang thiếu. Chỉ có nghĩa khi plan còn DRAFT. */
    public function getRawMaterialShortagesAttribute(): Collection
    {
        if ($this->status !== ProductionStatus::DRAFT) {
            return collect();
        }

        $this->loadMissing('rawMaterialRequirements.rawMaterial');

        return $this->rawMaterialRequirements->filter(
            fn($r) => $r->rawMaterial
                && $r->rawMaterial->available_quantity < (float) $r->required_quantity
        )->values();
    }

    /** Các dòng sản phẩm trong plan mà variant chưa khai công thức. */
    public function getItemsWithoutRecipeAttribute(): Collection
    {
        $this->loadMissing('items.productVariant.ingredients');

        return $this->items->filter(
            fn($item) => $item->productVariant && $item->productVariant->ingredients->isEmpty()
        )->values();
    }

    /** @return array{label: string, color: string} */
    public function materialStatus(): array
    {
        return match ($this->status) {
            ProductionStatus::DRAFT => match (true) {
                $this->items_without_recipe->isNotEmpty() => [
                    'label' => 'Thiếu công thức (' . $this->items_without_recipe->count() . ' SP)',
                    'color' => 'warning',
                ],
                $this->raw_material_shortages->isNotEmpty() => [
                    'label' => 'Thiếu ' . $this->raw_material_shortages->count() . ' nguyên liệu',
                    'color' => 'danger',
                ],
                default => ['label' => 'Đủ nguyên liệu', 'color' => 'success'],
            },
            ProductionStatus::PLANNED,
            ProductionStatus::IN_PROGRESS => ['label' => 'Đã giữ nguyên liệu', 'color' => 'info'],
            default => ['label' => '—', 'color' => 'gray'],
        };
    }
}
