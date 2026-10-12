<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],

            'deliveries' => DeliveryResource::collection($this->whenLoaded('deliveries')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),

            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status->value,
                'label' => $this->payment_status->label(),
            ],

            'pricing' => [
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discount_amount,
                'delivery_fee' => $this->delivery_fee,
                'total' => $this->total,
                'total_cancelled_amount' => $this->total_cancelled_amount,
                'net_payable' => $this->net_payable,
                'pending_cash_refund_amount' => $this->pending_cash_refund_amount,
                'total_cash_refunded' => $this->total_cash_refunded,
            ],

            'note' => $this->note,
            'completed_at' => $this->completed_at?->toISOString(),

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'payment_history' => PaymentStatusHistoryResource::collection($this->whenLoaded('paymentStatusHistories')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
