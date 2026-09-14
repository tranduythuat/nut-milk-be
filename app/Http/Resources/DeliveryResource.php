<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,

            'delivery_date' => $this->delivery_date?->format('Y-m-d'),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'address' => $this->address,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'note' => $this->note,

            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),

            'delivery_slot' => $this->whenLoaded(
                'deliverySlot',
                fn() => [
                    'id' => $this->deliverySlot->id,
                    'name' => $this->deliverySlot->name,
                    'start_time' => $this->deliverySlot->start_time,
                    'end_time' => $this->deliverySlot->end_time,
                ]
            ),

            'status_history' => DeliveryStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
