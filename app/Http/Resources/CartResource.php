<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');
        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($items),
            'total_quantity' => $items->sum('quantity'),
            'subtotal' => $items->sum(
                fn($item) => $item->quantity * $item->unit_price
            ),
        ];
    }
}
