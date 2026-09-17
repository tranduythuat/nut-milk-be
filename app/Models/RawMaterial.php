<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'unit',
        'stock',
        'reserved_quantity',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(ProductVariantIngredient::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(RawMaterialStockMovement::class)->latest();
    }

    public function planRequirements(): HasMany
    {
        return $this->hasMany(ProductionPlanRawMaterialRequirement::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return max(0, (float) $this->stock - (float) $this->reserved_quantity);
    }

    public function getIsLowStockAttribute(): bool
    {
        if ($this->min_stock === null) {
            return false;
        }

        return (float) $this->stock <= (float) $this->min_stock;
    }
}
