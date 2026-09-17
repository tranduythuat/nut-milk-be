<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantIngredientResource extends JsonResource
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
            'raw_material_id' => $this->raw_material_id,
            'raw_material_name' => $this->whenLoaded('rawMaterial', fn() => $this->rawMaterial->name),
            'unit' => $this->whenLoaded('rawMaterial', fn() => $this->rawMaterial->unit),
            'quantity' => (float) $this->quantity,
        ];
    }
}
