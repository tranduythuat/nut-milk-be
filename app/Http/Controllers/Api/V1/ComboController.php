<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComboListResource;
use App\Http\Resources\ComboResource;
use App\Models\Combo;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ComboController extends Controller
{
    /**
     * GET /api/v1/combos
     */
    public function index(Request $request)
    {
        $combos = QueryBuilder::for(Combo::class)
            ->where('is_active', true)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('slug'),
            )
            ->allowedSorts(
                'name',
                'price',
                'sort_order',
                'created_at',
            )
            ->defaultSort('sort_order')
            ->paginate(12)
            ->appends($request->query());

        return ComboListResource::collection($combos);
    }

    /**
     * GET /api/v1/combos/{slug}
     */
    public function show(string $slug)
    {
        $combo = Combo::query()
            ->with([
                'items.productVariant.product',
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return new ComboResource($combo);
    }
}
