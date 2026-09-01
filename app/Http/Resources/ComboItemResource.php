<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'variant' => [
                'id' => $this->productVariant->id,
                'name' => $this->productVariant->name,
                'attributes' => $this->productVariant->attributes,
                'price' => $this->productVariant->price,
            ],
            'product' => [
                'id' => $this->productVariant->product->id,
                'name' => $this->productVariant->product->name,
                'slug' => $this->productVariant->product->slug,
                'image' => $this->productVariant->product->image,
            ],
        ];
    }
}
