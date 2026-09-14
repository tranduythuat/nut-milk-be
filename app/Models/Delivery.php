<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'delivery_slot_id',
        'delivery_date',
        'status',
        'address',
        'recipient_name',
        'recipient_phone',
        'note',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'status' => DeliveryStatus::class,
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(
            DeliverySlot::class
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(
            DeliveryStatusHistory::class
        )->latest();
    }
}
