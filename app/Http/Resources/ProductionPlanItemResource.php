<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionPlanItemResource extends JsonResource
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

            'variant' => $this->whenLoaded(
                'productVariant',
                fn() => [
                    'id' => $this->productVariant->id,
                    'name' => $this->productVariant->name,
                    'product' => $this->when(
                        $this->productVariant->relationLoaded('product'),
                        fn() => [
                            'id' => $this->productVariant->product->id,
                            'name' => $this->productVariant->product->name,
                        ]
                    ),
                ]
            ),

            'planned_quantity' => $this->planned_quantity,
            'produced_quantity' => $this->produced_quantity,
            'remaining_quantity' => max(
                0,
                $this->planned_quantity - $this->produced_quantity
            ),

            'is_completed' => $this->produced_quantity >=
                $this->planned_quantity,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
