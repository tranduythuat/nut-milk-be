<?php

namespace App\Services;

use App\Models\ProductVariantIngredient;
use App\Models\RawMaterial;
use Illuminate\Support\Collection;

class RawMaterialRequirementService
{
    /**
     * @param Collection<int, float> $variantRequirements [product_variant_id => quantity]
     * @return Collection<int, float> [raw_material_id => quantity]
     */
    public function calculate(Collection $variantRequirements): Collection
    {
        if ($variantRequirements->isEmpty()) {
            return collect();
        }

        $ingredients = ProductVariantIngredient::query()
            ->whereIn('product_variant_id', $variantRequirements->keys())
            ->get();

        $requirements = collect();

        foreach ($ingredients as $ingredient) {
            $variantQuantity = $variantRequirements->get($ingredient->product_variant_id, 0);

            if ($variantQuantity <= 0) {
                continue;
            }

            $required = (float) $ingredient->quantity * $variantQuantity;

            $requirements->put(
                $ingredient->raw_material_id,
                ($requirements->get($ingredient->raw_material_id) ?? 0) + $required
            );
        }

        return $requirements;
    }

    /**
     * @param Collection<int, float> $requirements [raw_material_id => quantity]
     * @return array{feasible: bool, shortages: array}
     */
    public function checkFeasibility(Collection $requirements): array
    {
        if ($requirements->isEmpty()) {
            return ['feasible' => true, 'shortages' => []];
        }

        $materials = RawMaterial::query()
            ->whereIn('id', $requirements->keys())
            ->get()
            ->keyBy('id');

        $shortages = [];

        foreach ($requirements as $materialId => $requiredQuantity) {
            $material = $materials->get($materialId);
            $available = $material ? $material->available_quantity : 0;

            if ($available < $requiredQuantity) {
                $shortages[] = [
                    'raw_material_id' => $materialId,
                    'raw_material_name' => $material?->name,
                    'unit' => $material?->unit,
                    'required_quantity' => $requiredQuantity,
                    'available_quantity' => $available,
                    'shortage_quantity' => $requiredQuantity - $available,
                ];
            }
        }

        return ['feasible' => empty($shortages), 'shortages' => $shortages];
    }
}
