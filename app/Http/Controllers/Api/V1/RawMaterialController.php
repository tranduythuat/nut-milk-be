<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRawMaterialRequest;
use App\Http\Requests\UpdateRawMaterialRequest;
use App\Http\Requests\ReceiveRawMaterialStockRequest;
use App\Http\Requests\AdjustRawMaterialStockRequest;
use App\Http\Resources\RawMaterialResource;
use App\Models\RawMaterial;
use App\Services\RawMaterialInventoryService;
use Illuminate\Http\Request;
use RuntimeException;

class RawMaterialController extends Controller
{
    public function __construct(
        protected RawMaterialInventoryService $inventoryService
    ) {}

    public function index(Request $request)
    {
        $materials = RawMaterial::query()
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('low_stock'), fn($q) => $q->whereNotNull('min_stock')->whereColumn('stock', '<=', 'min_stock'))
            ->when($request->filled('name'), fn($q) => $q->where('name', 'like', '%' . $request->input('name') . '%'))
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return RawMaterialResource::collection($materials);
    }

    public function store(StoreRawMaterialRequest $request)
    {
        $material = RawMaterial::create([
            ...$request->validated(),
            'stock' => $request->validated('stock') ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['success' => true, 'data' => new RawMaterialResource($material)], 201);
    }

    public function show(RawMaterial $rawMaterial)
    {
        return response()->json(['success' => true, 'data' => new RawMaterialResource($rawMaterial)]);
    }

    public function update(UpdateRawMaterialRequest $request, RawMaterial $rawMaterial)
    {
        $rawMaterial->update($request->validated());

        return response()->json(['success' => true, 'data' => new RawMaterialResource($rawMaterial)]);
    }

    public function destroy(RawMaterial $rawMaterial)
    {
        $rawMaterial->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Nguyên liệu đã được vô hiệu hóa.']);
    }

    public function receive(ReceiveRawMaterialStockRequest $request, RawMaterial $rawMaterial)
    {
        try {
            $material = $this->inventoryService->receive(
                $rawMaterial,
                (float) $request->validated('quantity'),
                null,
                $request->validated('note')
            );

            return response()->json(['success' => true, 'data' => new RawMaterialResource($material)]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function adjust(AdjustRawMaterialStockRequest $request, RawMaterial $rawMaterial)
    {
        try {
            $material = $this->inventoryService->adjustTo(
                $rawMaterial,
                (float) $request->validated('stock'),
                $request->validated('note')
            );

            return response()->json(['success' => true, 'data' => new RawMaterialResource($material)]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
