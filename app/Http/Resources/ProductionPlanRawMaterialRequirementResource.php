<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionPlanRawMaterialRequirementResource extends JsonResource
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
            'raw_material' => $this->whenLoaded('rawMaterial', fn() => [
                'id' => $this->rawMaterial->id,
                'name' => $this->rawMaterial->name,
                'unit' => $this->rawMaterial->unit,
                'stock' => (float) $this->rawMaterial->stock,
                'available_quantity' => $this->rawMaterial->available_quantity,
            ]),
            'required_quantity' => (float) $this->required_quantity,
            'reserved_quantity' => (float) $this->reserved_quantity,
            'consumed_quantity' => (float) $this->consumed_quantity,
            'remaining_quantity' => $this->remaining_quantity,
        ];
    }
}
