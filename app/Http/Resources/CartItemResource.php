<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isProduct = !is_null(@$this->product_variant_id);

        return [
            'type' => $isProduct ? 'product' : 'combo',
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->quantity * $this->unit_price,
            'product' => $this->when(
                $isProduct,
                fn() => [
                    'id' => $this->productVariant?->product?->id,
                    'name' => $this->productVariant?->product?->name,
                    'slug' => $this->productVariant?->product?->slug,
                    'image' => $this->productVariant?->product?->image,
                    'variant' => [
                        'id' => $this->productVariant?->id,
                        'name' => $this->productVariant?->name,
                        'attributes' => $this->productVariant?->attributes,
                    ],
                ]
            ),
            'combo' => $this->when(
                !$isProduct,
                fn() => [
                    'id' => $this->combo?->id,
                    'name' => $this->combo?->name,
                    'slug' => $this->combo?->slug,
                    'image' => $this->combo?->image,
                ]
            ),
        ];
    }
}
