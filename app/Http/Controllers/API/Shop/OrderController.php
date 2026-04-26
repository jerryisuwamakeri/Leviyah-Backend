<?php

namespace App\Http\Controllers\API\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['items.product.primaryImage', 'transaction'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $order->load(['items.product.primaryImage', 'items.variant', 'transaction', 'address']);

        return response()->json($order);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'address_id'       => 'nullable|exists:addresses,id',
            'shipping_address' => 'required_without:address_id|array',
            'payment_method'   => 'required|in:paystack,whatsapp,bank_transfer,cash,pos',
            'guest_email'      => 'nullable|email|max:191',
            'guest_name'       => 'nullable|string|max:191',
            'notes'            => 'nullable|string|max:500',
        ]);

        $user = auth('sanctum')->user();

        if ($user) {
            $cart = Cart::with('items.product.variants', 'items.variant')
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $sessionId = $request->header('X-Cart-Session');
            if (!$sessionId) {
                return response()->json(['message' => 'Cart session not found.'], 422);
            }
            $cart = Cart::with('items.product.variants', 'items.variant')
                ->where('session_id', $sessionId)
                ->firstOrFail();
        }

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        foreach ($cart->items as $item) {
            if ($item->product->stock_quantity < $item->quantity && $item->product->track_inventory) {
                return response()->json([
                    'message' => "Insufficient stock for {$item->product->name}.",
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($cart, $data, $request, $user) {
            $shippingAddress = $data['shipping_address'] ?? [];

            $order = Order::create([
                'user_id'          => $user?->id,
                'guest_email'      => $user ? null : ($data['guest_email'] ?? null),
                'guest_name'       => $user ? null : ($data['guest_name'] ?? null),
                'address_id'       => $data['address_id'] ?? null,
                'payment_method'   => $data['payment_method'],
                'subtotal'         => $cart->subtotal,
                'discount_amount'  => $cart->discount_amount,
                'shipping_fee'     => 0,
                'total'            => $cart->total,
                'coupon_code'      => $cart->coupon_code,
                'notes'            => $data['notes'] ?? null,
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cart->items as $item) {
                $variant = $item->variant;
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name'       => $item->product->name,
                    'variant_info'       => $variant?->label,
                    'quantity'           => $item->quantity,
                    'unit_price'         => $item->unit_price,
                    'total_price'        => $item->line_total,
                    'thumbnail'          => $item->product->thumbnail,
                ]);

                if ($item->product->track_inventory) {
                    $item->product->decrement('stock_quantity', $item->quantity);
                }
            }

            $cart->items()->delete();
            $cart->update(['coupon_code' => null, 'discount_amount' => 0]);

            return $order;
        });

        return response()->json([
            'order'             => $order->load('items'),
            'payment_reference' => $order->order_number,
        ], 201);
    }

    public function verifyPayment(Request $request, Order $order)
    {
        $request->validate(['reference' => 'required|string']);

        $user = auth('sanctum')->user();

        if ($user && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Transaction::updateOrCreate(
            ['reference' => $request->reference],
            [
                'order_id'  => $order->id,
                'user_id'   => $user?->id,
                'gateway'   => $order->payment_method,
                'status'    => 'success',
                'amount'    => $order->total,
                'paid_at'   => now(),
            ]
        );

        $order->update(['payment_status' => 'paid', 'status' => 'confirmed', 'paid_at' => now()]);

        return response()->json(['message' => 'Payment verified.', 'order' => $order->fresh()]);
    }
}
