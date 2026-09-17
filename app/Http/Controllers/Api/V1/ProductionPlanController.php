<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductionStatus;
use App\Models\ProductionPlanItem;
use App\Models\ProductionPlan;
use App\Services\ProductionPlanningService;
use App\Services\ProductionQuantityService;
use App\Services\ProductionStatusService;
use App\Services\ProductionCompletionService;
use App\Services\RawMaterialRequirementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductionQuantityRequest;
use App\Http\Requests\UpdateProductionStatusRequest;
use App\Http\Resources\ProductionPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
                'rawMaterialRequirements.rawMaterial',
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

    public function show(ProductionPlan $productionPlan): JsonResponse
    {
        $productionPlan->load([
            'items.productVariant.product',
            'rawMaterialRequirements.rawMaterial',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ProductionPlanResource($productionPlan),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        try {
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
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function feasibility(
        ProductionPlan $productionPlan,
        RawMaterialRequirementService $service
    ): JsonResponse {
        $productionPlan->load('rawMaterialRequirements.rawMaterial');

        $requirements = $productionPlan->rawMaterialRequirements
            ->pluck('required_quantity', 'raw_material_id')
            ->map(fn($qty) => (float) $qty);

        return response()->json([
            'success' => true,
            'data' => $service->checkFeasibility($requirements),
        ]);
    }

    public function updateQuantity(
        UpdateProductionQuantityRequest $request,
        ProductionPlan $productionPlan,
        ProductionPlanItem $productionPlanItem,
        ProductionQuantityService $service
    ): JsonResponse {
        try {
            $plan = $service->update(
                $productionPlan,
                $productionPlanItem,
                (int) $request->validated('produced_quantity')
            );

            return response()->json([
                'success' => true,
                'data' => new ProductionPlanResource($plan),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function updateStatus(
        UpdateProductionStatusRequest $request,
        ProductionPlan $productionPlan,
        ProductionStatusService $service
    ): JsonResponse {
        try {
            $plan = $service->updateStatus(
                $productionPlan,
                ProductionStatus::from($request->validated('status')),
                $request->validated('note')
            );

            return response()->json([
                'success' => true,
                'data' => new ProductionPlanResource($plan),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function complete(
        ProductionPlan $productionPlan,
        Request $request,
        ProductionCompletionService $service
    ): JsonResponse {
        try {
            $plan = $service->complete(
                $productionPlan,
                $request->input('note')
            );

            return response()->json([
                'success' => true,
                'data' => new ProductionPlanResource($plan),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
