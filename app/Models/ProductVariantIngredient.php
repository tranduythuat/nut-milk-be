<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantIngredient extends Model
{
    protected $fillable = [
        'product_variant_id',
        'raw_material_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
