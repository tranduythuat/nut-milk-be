<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\AuthorizesOrderOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\LookupOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Requests\UpdatePaymentStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderCancellationService;
use App\Services\OrderStatusService;
use App\Services\PaymentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    use AuthorizesOrderOwner;

    public function __construct(
        protected OrderStatusService $orderStatusService,
        protected PaymentStatusService $paymentStatusService,
    ) {}

    /** STAFF: GET /orders/{order} */
    public function show(Order $order): OrderResource
    {
        return new OrderResource($this->loadDetail($order));
    }

    /** STAFF: GET /orders?status=&phone=&order_number= */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['items', 'deliveries', 'refunds', 'statusHistories'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('phone'), fn($q) => $q->where('customer_phone', 'like', '%' . $request->input('phone') . '%'))
            ->when($request->filled('order_number'), fn($q) => $q->where('order_number', $request->input('order_number')))
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return OrderResource::collection($orders);
    }

    /** PUBLIC: POST /orders/lookup  { order_number, phone } */
    public function lookup(LookupOrderRequest $request): JsonResponse|OrderResource
    {
        $order = Order::query()
            ->where('order_number', $request->validated('order_number'))
            ->first();

        if (! $order || ! $order->phoneMatches($request->validated('phone'))) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng.',
            ], 404);
        }

        return new OrderResource($this->loadDetail($order));
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order
    ): JsonResponse {
        try {
            $order = $this->orderStatusService->update(
                $order,
                OrderStatus::from($request->validated('status')),
                $request->validated('note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data' => new OrderResource($order),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function updatePaymentStatus(
        UpdatePaymentStatusRequest $request,
        Order $order
    ): JsonResponse {
        try {
            $order = $this->paymentStatusService->update(
                $order,
                PaymentStatus::from($request->validated('status')),
                $request->validated('note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully.',
                'data' => new OrderResource($order),
            ]);
        } catch (RuntimeException $exception) {
            // Trước đây thiếu try/catch -> chuyển trạng thái sai trả 500.
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /** PUBLIC (chủ đơn qua phone) hoặc STAFF: POST /orders/{order}/cancel */
    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        OrderCancellationService $service
    ): JsonResponse {
        $this->authorizeOrderOwnerOrStaff($request, $order);

        try {
            $order = $service->cancel($order, $request->validated('note'));

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

    protected function loadDetail(Order $order): Order
    {
        return $order->load([
            'items.components.productVariant',
            'deliveries.deliverySlot',
            'deliveries.items.orderItem',
            'deliveries.statusHistories',
            'refunds',
            'statusHistories',
            'paymentStatusHistories',
        ]);
    }
}
