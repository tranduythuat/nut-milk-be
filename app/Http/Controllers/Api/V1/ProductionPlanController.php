<?php

namespace App\Http\Controllers\Api\V1;


use App\Enums\ProductionStatus;
use App\Models\ProductionPlanItem;
use App\Models\ProductionPlan;
use App\Services\ProductionPlanningService;
use App\Services\ProductionQuantityService;
use App\Services\ProductionStatusService;
use App\Services\ProductionCompletionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductionQuantityRequest;
use App\Http\Requests\UpdateProductionStatusRequest;
use App\Http\Resources\ProductionPlanResource;
use Illuminate\Http\Request;

class ProductionPlanController extends Controller
{
    public function __construct(
        protected ProductionPlanningService $service
    ) {}

    public function index(Request $request)
    {
        $plans = ProductionPlan::query()
            ->with([
                'items.productVariant.product',
            ])
            ->when(
                $request->filled('production_date'),
                fn($query) => $query->whereDate(
                    'production_date',
                    $request->input('production_date')
                )
            )
            ->when(
                $request->filled('status'),
                fn($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )
            ->orderByDesc('production_date')
            ->paginate(20);

        return ProductionPlanResource::collection($plans);
    }

    public function show(ProductionPlan $productionPlan)
    {
        $productionPlan->load([
            'items.productVariant.product',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ProductionPlanResource($productionPlan),
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'production_date' => [
                'required',
                'date_format:Y-m-d',
            ],
        ]);

        $plan = $this->service->generate(
            $validated['production_date']
        );

        return response()->json([
            'success' => true,
            'data' => new ProductionPlanResource($plan),
        ]);
    }

    public function updateQuantity(
        UpdateProductionQuantityRequest $request,
        ProductionPlan $productionPlan,
        ProductionPlanItem $productionPlanItem,
        ProductionQuantityService $service
    ) {
        $plan = $service->update(
            $productionPlan,
            $productionPlanItem,
            (int) $request->validated('produced_quantity')
        );

        return response()->json([
            'success' => true,
            'data' => new ProductionPlanResource($plan),
        ]);
    }

    public function updateStatus(
        UpdateProductionStatusRequest $request,
        ProductionPlan $productionPlan,
        ProductionStatusService $service
    ) {
        $plan = $service->updateStatus(
            $productionPlan,
            ProductionStatus::from($request->validated['status']),
            $request->validated['note'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => new ProductionPlanResource($plan),
        ]);
    }

    public function complete(
        ProductionPlan $productionPlan,
        Request $request,
        ProductionCompletionService $service
    ) {
        $plan = $service->complete(
            $productionPlan,
            $request->input('note')
        );

        return response()->json([
            'success' => true,
            'data' => new ProductionPlanResource($plan),
        ]);
    }
}
