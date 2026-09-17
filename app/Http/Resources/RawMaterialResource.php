<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawMaterialResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'unit' => $this->unit,
            'stock' => (float) $this->stock,
            'reserved_quantity' => (float) $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'min_stock' => $this->min_stock !== null ? (float) $this->min_stock : null,
            'is_low_stock' => $this->is_low_stock,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
