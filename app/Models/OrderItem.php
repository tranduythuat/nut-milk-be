<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',

        'product_variant_id',
        'combo_id',

        'item_name',
        'variant_name',
        'attributes',

        'quantity',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',

            'quantity' => 'integer',

            'unit_price' => 'integer',

            'total_price' => 'integer',
        ];
    }

    /**
     * Order item belongs to an order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Original product variant.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Original combo.
     */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }
}
