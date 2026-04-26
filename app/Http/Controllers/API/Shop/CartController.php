<?php

namespace App\Http\Controllers\API\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function resolveCart(Request $request): Cart
    {
        // Try Sanctum token auth even on public routes
        $user = auth('sanctum')->user();
        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = $request->header('X-Cart-Session');
        if (!$sessionId) {
            $sessionId = 'guest-' . \Str::uuid();
        }
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    private function cartResponse(Cart $cart): \Illuminate\Http\JsonResponse
    {
        $cart->load(['items.product.primaryImage', 'items.variant']);
        return response()->json($cart);
    }

    public function index(Request $request)
    {
        return $this->cartResponse($this->resolveCart($request));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity'           => 'required|integer|min:1|max:99',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = isset($data['product_variant_id'])
            ? ProductVariant::findOrFail($data['product_variant_id'])
            : null;

        if (!$product->is_active) {
            return response()->json(['message' => 'Product is unavailable.'], 422);
        }

        $unitPrice = $variant
            ? ($variant->sale_price ?? $variant->price)
            : ($product->sale_price ?? $product->base_price);

        $cart = $this->resolveCart($request);

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $data['quantity']);
            $existing->update(['unit_price' => $unitPrice]);
        } else {
            CartItem::create([
                'cart_id'            => $cart->id,
                'product_id'         => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity'           => $data['quantity'],
                'unit_price'         => $unitPrice,
            ]);
        }

        return $this->cartResponse($this->resolveCart($request));
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:99']);
        $cartItem->update($data);
        return $this->cartResponse($this->resolveCart($request));
    }

    public function remove(Request $request, CartItem $cartItem)
    {
        $cartItem->delete();
        return $this->cartResponse($this->resolveCart($request));
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $cart   = $this->resolveCart($request);
        $coupon = Coupon::where('code', strtoupper($request->code))->firstOrFail();

        if (!$coupon->isValid()) {
            return response()->json(['message' => 'This coupon is invalid or expired.'], 422);
        }

        $discount = $coupon->calculateDiscount($cart->subtotal);

        if ($discount <= 0) {
            return response()->json(['message' => 'Minimum order not met for this coupon.'], 422);
        }

        $cart->update(['coupon_code' => $coupon->code, 'discount_amount' => $discount]);

        return $this->cartResponse($this->resolveCart($request));
    }

    public function removeCoupon(Request $request)
    {
        $cart = $this->resolveCart($request);
        $cart->update(['coupon_code' => null, 'discount_amount' => 0]);
        return $this->cartResponse($this->resolveCart($request));
    }

    public function clear(Request $request)
    {
        $cart = $this->resolveCart($request);
        $cart->items()->delete();
        $cart->update(['coupon_code' => null, 'discount_amount' => 0]);
        return response()->json(['message' => 'Cart cleared.']);
    }
}
