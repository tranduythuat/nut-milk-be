<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories
     */
    public function index(Request $request)
    {
        $categories = QueryBuilder::for(Category::class)
            ->where('is_active', true)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('slug'),
            )
            ->allowedSorts(
                'name',
                'sort_order',
                'created_at',
            )
            ->defaultSort('sort_order')
            ->paginate(20)
            ->appends($request->query());

        return CategoryResource::collection($categories);
    }

    /**
     * GET /api/v1/categories/{slug}
     */
    public function show(string $slug)
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return new CategoryResource($category);
    }
}
