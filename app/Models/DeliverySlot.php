<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverySlot extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'max_orders',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'max_orders' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
