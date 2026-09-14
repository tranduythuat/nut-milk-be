<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ComboController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\ProductionPlanController;
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
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::patch('/orders/{order}/payment-status', [OrderController::class, 'updatePaymentStatus']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::prefix('delivery')->group(function () {
        Route::get('available-dates', [DeliveryController::class, 'availableDates']);
        Route::get('availability', [DeliveryController::class, 'availability']);
        Route::patch('/{delivery}/status', [DeliveryController::class, 'updateStatus']);
    });

    // production-plans
    Route::prefix('production-plans')->group(function () {
        Route::get('/', [ProductionPlanController::class, 'index']);
        Route::post('generate', [ProductionPlanController::class, 'generate']);
        Route::post('/{productionPlan}/complete', [ProductionPlanController::class, 'complete']);
        Route::get('/{productionPlan}', [ProductionPlanController::class, 'show']);

        Route::patch('{productionPlan}/status', [ProductionPlanController::class, 'updateStatus']);
        Route::patch(
            '{productionPlan}/items/{productionPlanItem}/quantity',
            [ProductionPlanController::class, 'updateQuantity']
        );
    });
});
