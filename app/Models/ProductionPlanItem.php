<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPlanItem extends Model
{
    protected $fillable = [
        'production_plan_id',
        'product_variant_id',
        'planned_quantity',
        'produced_quantity',
    ];

    protected $casts = [
        'planned_quantity' => 'integer',
        'produced_quantity' => 'integer',
    ];

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(
            ProductionPlan::class
        );
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class
        );
    }
}
