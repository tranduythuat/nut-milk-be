<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'variant_name' => $this->variant_name,
            'attributes' => $this->attributes,
            'quantity' => $this->quantity,

            'product_variant' => $this->whenLoaded(
                'productVariant',
                fn() => [
                    'id' => $this->productVariant->id,
                    'name' => $this->productVariant->name,
                ]
            ),
        ];
    }
}
