<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RefundController extends Controller
{
    public function index(Order $order): JsonResponse
    {
        $refunds = $order->refunds()->with('delivery')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => RefundResource::collection($refunds),
        ]);
    }

    public function complete(
        CompleteRefundRequest $request,
        Refund $refund,
        RefundService $service
    ): JsonResponse {
        try {
            $refund = $service->completeCashRefund($refund, $request->validated('note'));

            return response()->json([
                'success' => true,
                'message' => 'Đã xác nhận hoàn tiền thành công.',
                'data' => new RefundResource($refund),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
