<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ComboController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\ProductionPlanController;
use App\Http\Controllers\Api\V1\RawMaterialController;
use App\Http\Controllers\Api\V1\ProductVariantIngredientController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RefundController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is healthy',
            'data' => [
                'app' => 'nut-milk-backend',
                'version' => 'v1',
            ],
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | PUBLIC — khách hàng, không cần auth
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    // combos
    Route::get('/combos', [ComboController::class, 'index']);
    Route::get('/combos/{slug}', [ComboController::class, 'show']);

    // cart
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{item}', [CartController::class, 'update']);
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // checkout
    Route::post('/checkout', [CheckoutController::class, 'store']);

    // orders

    // Khách hàng tra cứu đơn của chính họ bằng SĐT — vẫn công khai,
    // vì hiện chưa gắn Order với tài khoản đăng nhập (guest checkout).
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);


    // Khách được phép tự hủy đơn CỦA HỌ khi còn ở trạng thái cho phép.
    // (Không cần role staff — đây là quyền của chủ đơn hàng, xác thực
    // theo route model binding order_id trong CancelOrderRequest nếu
    // cần thắt chặt thêm bằng SĐT khớp, xem ghi chú cuối phần này.)
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::prefix('delivery')->group(function () {
        Route::get('available-dates', [DeliveryController::class, 'availableDates']);
        Route::get('availability', [DeliveryController::class, 'availability']);

        Route::post('/{delivery}/cancel', [DeliveryController::class, 'cancel']);
    });


    /*
    |--------------------------------------------------------------------------
    | STAFF-ONLY — vận hành nội bộ, bắt buộc auth:sanctum + role staff/admin
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::patch('/orders/{order}/payment-status', [OrderController::class, 'updatePaymentStatus']);
        Route::get('/orders/{order}/refunds', [RefundController::class, 'index']);
        Route::post('/refunds/{refund}/complete', [RefundController::class, 'complete']);

        Route::prefix('delivery')->group(function () {
            Route::patch('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
        });

        // công thức (BOM) theo từng variant
        Route::prefix('product-variants/{productVariant}/ingredients')->group(function () {
            Route::get('/', [ProductVariantIngredientController::class, 'index']);
            Route::put('/', [ProductVariantIngredientController::class, 'sync']);
        });
        // production-plans
        Route::prefix('production-plans')->group(function () {
            Route::get('/', [ProductionPlanController::class, 'index']);
            Route::post('generate', [ProductionPlanController::class, 'generate']);
            Route::post('/{productionPlan}/complete', [ProductionPlanController::class, 'complete']);
            Route::get('/{productionPlan}', [ProductionPlanController::class, 'show']);
            Route::get('/{productionPlan}/feasibility', [ProductionPlanController::class, 'feasibility']);
            Route::patch('{productionPlan}/status', [ProductionPlanController::class, 'updateStatus']);
            Route::patch(
                '{productionPlan}/items/{productionPlanItem}/quantity',
                [ProductionPlanController::class, 'updateQuantity']
            );
        });
        // raw materials
        Route::prefix('raw-materials')->group(function () {
            Route::get('/', [RawMaterialController::class, 'index']);
            Route::post('/', [RawMaterialController::class, 'store']);
            Route::get('/{rawMaterial}', [RawMaterialController::class, 'show']);
            Route::patch('/{rawMaterial}', [RawMaterialController::class, 'update']);
            Route::delete('/{rawMaterial}', [RawMaterialController::class, 'destroy']);
            Route::post('/{rawMaterial}/receive', [RawMaterialController::class, 'receive']);
            Route::post('/{rawMaterial}/adjust', [RawMaterialController::class, 'adjust']);
        });
    });
});
