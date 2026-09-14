<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Requests\UpdatePaymentStatusRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Services\OrderCancellationService;
use App\Services\PaymentStatusService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderStatusService $orderStatusService,
        protected PaymentStatusService $paymentStatusService,
    ) {}

    public function show(
        Order $order
    ): OrderResource {
        $order->load([
            'items.productVariant.product',
            'items.components.productVariant.product',
            'delivery.deliverySlot',
            'statusHistories',
            'paymentStatusHistories',
        ]);

        return new OrderResource($order);
    }

    public function index(Request $request)
    {
        $request->validate([
            'phone' => [
                'required',
                'string',
                'max:20',
            ],
        ]);

        $orders = Order::query()
            ->with([
                'items',
                'statusHistories',
            ])
            ->where('customer_phone', $request->phone)
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
        OrderStatusService $service
    ): JsonResponse {
        try {
            $order = $service->update(
                $order,
                OrderStatus::from(
                    $request->validated('status')
                ),
                $request->validated('note')
            );

            return response()->json([
                'message' => 'Order status updated successfully.',
                'data' => new OrderResource($order),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function updatePaymentStatus(
        UpdatePaymentStatusRequest $request,
        Order $order
    ) {
        $order = $this->paymentStatusService->update(
            $order,
            PaymentStatus::from(
                $request->validated('status')
            ),
            $request->validated('note')
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully.',
            'data' => new OrderResource($order),
        ]);
    }

    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        OrderCancellationService $service
    ): JsonResponse {
        try {
            $order = $service->cancel(
                $order,
                $request->validated('note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Đơn hàng đã được hủy.',
                'data' => new OrderResource($order),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
