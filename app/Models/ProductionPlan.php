<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ProductionStatus;

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
}
