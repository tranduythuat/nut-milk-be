<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncProductVariantIngredientsRequest;
use App\Http\Resources\ProductVariantIngredientResource;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantIngredientController extends Controller
{
    public function index(ProductVariant $productVariant)
    {
        return ProductVariantIngredientResource::collection(
            $productVariant->ingredients()->with('rawMaterial')->get()
        );
    }

    public function sync(SyncProductVariantIngredientsRequest $request, ProductVariant $productVariant)
    {
        DB::transaction(function () use ($request, $productVariant) {
            $productVariant->ingredients()->delete();

            foreach ($request->validated('ingredients') as $ingredient) {
                $productVariant->ingredients()->create([
                    'raw_material_id' => $ingredient['raw_material_id'],
                    'quantity' => $ingredient['quantity'],
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => ProductVariantIngredientResource::collection(
                $productVariant->ingredients()->with('rawMaterial')->get()
            ),
        ]);
    }
}
