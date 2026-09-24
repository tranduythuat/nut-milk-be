<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncProductVariantIngredientsRequest;
use App\Http\Resources\ProductVariantIngredientResource;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductVariantIngredientController extends Controller
{
    public function index(ProductVariant $productVariant)
    {
        return ProductVariantIngredientResource::collection(
            $productVariant->ingredients()->with('rawMaterial')->get()
        );
    }

    public function sync(
        SyncProductVariantIngredientsRequest $request,
        ProductVariant $productVariant,
        UnitConversionService $units
    ) {
        $ingredients = $request->validated('ingredients');

        $materials = RawMaterial::query()
            ->whereIn('id', collect($ingredients)->pluck('raw_material_id'))
            ->get()
            ->keyBy('id');

        try {
            DB::transaction(function () use ($ingredients, $productVariant, $materials, $units) {
                $productVariant->ingredients()->delete();

                foreach ($ingredients as $ingredient) {
                    $material = $materials->get($ingredient['raw_material_id']);
                    $inputUnit = $ingredient['unit'] ?? $material->unit;

                    $baseQuantity = $inputUnit === $material->unit
                        ? (float) $ingredient['quantity']
                        : $units->convert((float) $ingredient['quantity'], $inputUnit, $material->unit);

                    $productVariant->ingredients()->create([
                        'raw_material_id' => $ingredient['raw_material_id'],
                        'quantity' => $baseQuantity,
                    ]);
                }
            });
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ProductVariantIngredientResource::collection(
                $productVariant->ingredients()->with('rawMaterial')->get()
            ),
        ]);
    }
}
