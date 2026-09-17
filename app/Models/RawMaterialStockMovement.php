<?php

namespace App\Models;

use App\Enums\RawMaterialMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RawMaterialStockMovement extends Model
{
    protected $fillable = [
        'raw_material_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => RawMaterialMovementType::class,
            'quantity' => 'decimal:4',
            'stock_before' => 'decimal:4',
            'stock_after' => 'decimal:4',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
