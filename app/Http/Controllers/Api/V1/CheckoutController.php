<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    public function store(
        CheckoutRequest $request
    ): JsonResponse {
        $sessionId = $request->header(
            'X-Cart-Session'
        );

        if (! $sessionId) {
            return response()->json([
                'message' => 'Cart session is required.',
            ], 422);
        }

        $cart = Cart::query()
            ->where('session_id', $sessionId)
            ->first();

        if (! $cart) {
            return response()->json([
                'message' => 'Giỏ hàng không tồn tại.',
            ], 404);
        }

        try {
            $order = $this->checkoutService->checkout(
                $sessionId,
                $request->validated()
            );

            return response()->json([
                'message' => 'Checkout successful.',
                'data' => new OrderResource($order),
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
