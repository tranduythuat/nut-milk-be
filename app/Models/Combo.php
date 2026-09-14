<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = ['price' => 'integer', 'is_active' => 'boolean',];
    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
