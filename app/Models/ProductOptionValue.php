<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductOptionValue extends Model
{
    protected $fillable = [
        'product_option_id',
        'name',
        'code',
        'sort_order',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_option_values'
        );
    }
}
