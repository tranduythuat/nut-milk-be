<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',

        'customer_name',
        'customer_phone',
        'customer_address',
        'note',

        'subtotal',
        'delivery_fee',
        'discount_amount',
        'total',

        'status',
        'payment_status',
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
        ];
    }

    /**
     * Order belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order has many order items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(
            OrderStatusHistory::class
        )->latest();
    }

    public function paymentStatusHistories(): HasMany
    {
        return $this->hasMany(
            PaymentStatusHistory::class
        )->latest();
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
