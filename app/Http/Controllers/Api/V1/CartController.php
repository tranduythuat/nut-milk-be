<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Combo;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * GET /api/v1/cart
     */
    public function show(Request $request)
    {
        $cart = $this->getCart($request);

        return new CartResource(
            $this->loadCart($cart)
        );
    }

    /**
     * POST /api/v1/cart/items
     */
    public function store(
        AddCartItemRequest $request
    ) {
        $cart = $this->getCart($request);

        $data = $request->validated();

        if (!empty($data['product_variant_id'])) {
            $variant = ProductVariant::query()
                ->where('is_active', true)
                ->findOrFail($data['product_variant_id']);

            $this->cartService->addProduct(
                $cart,
                $variant,
                $data['quantity']
            );
        }

        if (!empty($data['combo_id'])) {
            $combo = Combo::query()
                ->where('is_active', true)
                ->findOrFail($data['combo_id']);

            $this->cartService->addCombo(
                $cart,
                $combo,
                $data['quantity']
            );
        }

        return new CartResource(
            $this->loadCart($cart->fresh())
        );
    }

    /**
     * PATCH /api/v1/cart/items/{item}
     */
    public function update(
        UpdateCartItemRequest $request,
        int $item
    ) {
        $cart = $this->getCart($request);

        $this->cartService->updateItem(
            $cart,
            $item,
            $request->validated('quantity')
        );

        return new CartResource(
            $this->loadCart($cart->fresh())
        );
    }

    /**
     * DELETE /api/v1/cart/items/{item}
     */
    public function destroy(
        Request $request,
        int $item
    ) {
        $cart = $this->getCart($request);

        $this->cartService->removeItem(
            $cart,
            $item
        );

        return new CartResource(
            $this->loadCart($cart->fresh())
        );
    }

    /**
     * DELETE /api/v1/cart
     */
    public function clear(Request $request)
    {
        $cart = $this->getCart($request);

        $this->cartService->clear($cart);

        return new CartResource(
            $this->loadCart($cart->fresh())
        );
    }

    private function getCart(Request $request)
    {
        return $this->cartService->getOrCreateCart(
            $request->user()?->id,
            $request->header('X-Cart-Session')
        );
    }

    private function loadCart($cart)
    {
        return $cart->load([
            'items.productVariant.product',
            'items.combo',
        ]);
    }
}
