<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductListResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     */
    public function index(Request $request)
    {
        $products = QueryBuilder::for(Product::class)
            ->with([
                'variants' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('sort_order');
                },
            ])
            ->where('is_active', true)
            ->allowedFilters(
                AllowedFilter::exact('category_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('slug'),
            )
            ->allowedSorts(
                'name',
                'sort_order',
                'created_at',
            )
            ->defaultSort('sort_order')
            ->paginate(12)
            ->appends($request->query());

        return ProductListResource::collection($products);
    }

    /**
     * GET /api/v1/products/{slug}
     */
    public function show(string $slug)
    {
        $product = Product::with([
            'category',
            'variants' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },
        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
