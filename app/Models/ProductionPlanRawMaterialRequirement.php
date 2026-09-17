<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPlanRawMaterialRequirement extends Model
{
    protected $fillable = [
        'production_plan_id',
        'raw_material_id',
        'required_quantity',
        'reserved_quantity',
        'consumed_quantity',
    ];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'consumed_quantity' => 'decimal:4',
        ];
    }

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, (float) $this->required_quantity - (float) $this->consumed_quantity);
    }
}
