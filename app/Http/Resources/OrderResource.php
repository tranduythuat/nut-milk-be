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

            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],

            'delivery' => [
                'fee' => $this->delivery_fee,
                'date' => $this->delivery?->delivery_date?->toDateString(),

                'slot' => $this->when(
                    $this->delivery?->deliverySlot,
                    fn() => [
                        'id' => $this->delivery->deliverySlot->id,
                        'name' => $this->delivery->deliverySlot->name,
                        'start_time' => $this->delivery->deliverySlot->start_time,
                        'end_time' => $this->delivery->deliverySlot->end_time,
                    ]
                ),

                'status' => $this->delivery?->status?->value,

                'status_label' => $this->delivery?->status?->label(),

                'recipient' => [
                    'name' => $this->delivery?->recipient_name,
                    'phone' => $this->delivery?->recipient_phone,
                    'address' => $this->delivery?->address,
                ],

                'note' => $this->delivery?->note,

                'shipped_at' => $this->delivery?->shipped_at?->toISOString(),

                'delivered_at' => $this->delivery?->delivered_at?->toISOString(),
            ],

            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'label' => $this->payment_status->label(),
            ],

            'pricing' => [
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discount_amount,
                'delivery_fee' => $this->delivery_fee,
                'total' => $this->total,
            ],

            'note' => $this->note,

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'components' => OrderItemComponentResource::collection(
                $this->whenLoaded('components')
            ),

            'history' => OrderStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),

            'payment_history' => PaymentStatusHistoryResource::collection(
                $this->whenLoaded('paymentStatusHistories')
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
