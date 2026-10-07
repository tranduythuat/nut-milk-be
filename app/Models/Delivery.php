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
        'item_subtotal',
        'delivery_fee',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'status' => DeliveryStatus::class,
        'item_subtotal' => 'integer',
        'delivery_fee' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class)->latest();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** Tổng giá trị đợt giao này = tiền hàng + phí ship riêng của đợt. */
    public function getTotalAttribute(): int
    {
        return $this->item_subtotal + $this->delivery_fee;
    }
}
