<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function products(Request $request)
    {
        $query = Product::with(['primaryImage', 'variants' => fn($q) => $q->active()->where('stock_quantity', '>', 0)])
            ->active()
            ->where(fn($q) => $q->where('stock_quantity', '>', 0)->orWhere('has_variants', true));

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', $request->search);
        }
        if ($request->category_id) $query->where('category_id', $request->category_id);

        return response()->json($query->limit(50)->get());
    }

    public function createSale(Request $request)
    {
        $data = $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variant_id'   => 'nullable|exists:product_variants,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'payment_method'       => 'required|in:cash,pos,bank_transfer',
            'amount_tendered'      => 'nullable|numeric|min:0',
            'discount_amount'      => 'nullable|numeric|min:0',
            'customer_name'        => 'nullable|string|max:255',
            'customer_phone'       => 'nullable|string|max:20',
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $subtotal = 0;
            $items    = [];

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $variant = isset($item['variant_id']) ? ProductVariant::find($item['variant_id']) : null;

                $unitPrice = $variant
                    ? (float) ($variant->sale_price ?? $variant->price)
                    : (float) ($product->sale_price ?? $product->base_price);

                $subtotal += $unitPrice * $item['quantity'];

                $items[] = [
                    'product'   => $product,
                    'variant'   => $variant,
                    'quantity'  => $item['quantity'],
                    'unitPrice' => $unitPrice,
                ];
            }

            $discount = (float) ($data['discount_amount'] ?? 0);
            $total    = max(0, $subtotal - $discount);

            $order = Order::create([
                'payment_method'  => $data['payment_method'],
                'status'          => 'delivered',
                'payment_status'  => 'paid',
                'subtotal'        => $subtotal,
                'discount_amount' => $discount,
                'shipping_fee'    => 0,
                'total'           => $total,
                'paid_at'         => now(),
                'notes'           => !empty($data['customer_name'])
                    ? "POS Sale - {$data['customer_name']}" . (!empty($data['customer_phone']) ? " ({$data['customer_phone']})" : '')
                    : 'POS Sale',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $item['product']->id,
                    'product_variant_id' => $item['variant']?->id,
                    'product_name'       => $item['product']->name,
                    'variant_info'       => $item['variant']?->label,
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unitPrice'],
                    'total_price'        => $item['unitPrice'] * $item['quantity'],
                    'thumbnail'          => $item['product']->thumbnail,
                ]);

                if ($item['product']->track_inventory) {
                    $item['product']->decrement('stock_quantity', $item['quantity']);
                }
            }

            Transaction::create([
                'order_id'  => $order->id,
                'reference' => 'POS-' . strtoupper(uniqid()),
                'gateway'   => $data['payment_method'],
                'status'    => 'success',
                'amount'    => $total,
                'paid_at'   => now(),
            ]);

            activity('pos')
                ->causedBy($request->user())
                ->withProperties(['order_number' => $order->order_number, 'total' => $total])
                ->log("POS sale completed: {$order->order_number}");

            return $order;
        });

        $change = isset($data['amount_tendered'])
            ? max(0, (float) $data['amount_tendered'] - $order->total)
            : 0;

        return response()->json([
            'order'  => $order->load('items'),
            'change' => $change,
        ], 201);
    }
}
