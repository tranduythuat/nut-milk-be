<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function paymentStatusHistories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class)->latest();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Tổng tiền đã bị "gỡ" khỏi đơn do các đợt giao bị hủy — tính
     * ngay lập tức khi hủy (bất kể Refund.status là pending hay
     * completed), vì đây phản ánh GIÁ TRỊ ĐÚNG của đơn sau điều
     * chỉnh, không phụ thuộc tiến độ xử lý tiền thật của staff.
     */
    public function getTotalCancelledAmountAttribute(): int
    {
        return (int) $this->refunds()->sum('amount');
    }

    /** Số tiền ĐÚNG khách cần trả/đã trả sau khi trừ các đợt đã hủy. */
    public function getNetPayableAttribute(): int
    {
        return max(0, $this->total - $this->total_cancelled_amount);
    }

    /**
     * Tiền mặt staff CÒN NỢ phải chuyển lại khách (chỉ tính loại
     * cash_refund, vì cod_adjustment không có dòng tiền thật).
     */
    public function getPendingCashRefundAmountAttribute(): int
    {
        return (int) $this->refunds()
            ->where('type', 'cash_refund')
            ->where('status', RefundStatus::PENDING->value)
            ->sum('amount');
    }

    /** Tiền mặt ĐÃ thực sự chuyển lại khách (đã staff xác nhận). */
    public function getTotalCashRefundedAttribute(): int
    {
        return (int) $this->refunds()
            ->where('type', 'cash_refund')
            ->where('status', RefundStatus::COMPLETED->value)
            ->sum('amount');
    }
}
