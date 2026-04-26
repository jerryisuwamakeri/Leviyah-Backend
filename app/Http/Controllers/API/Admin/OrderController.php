<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user:id,name,email,phone', 'items', 'transaction']);

        if ($request->status)         $query->where('status', $request->status);
        if ($request->payment_status) $query->where('payment_status', $request->payment_status);
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', "%{$request->search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%"));
            });
        }
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        return response()->json($query->latest()->paginate($request->per_page ?? 20));
    }

    public function show(Order $order)
    {
        return response()->json($order->load([
            'user:id,name,email,phone',
            'items.product.primaryImage',
            'items.variant',
            'transaction',
            'address',
        ]));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status'          => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'tracking_number' => 'nullable|string',
            'notes'           => 'nullable|string',
        ]);

        $timestamps = [
            'shipped'   => 'shipped_at',
            'delivered' => 'delivered_at',
        ];

        if (isset($timestamps[$data['status']])) {
            $data[$timestamps[$data['status']]] = now();
        }

        $order->update($data);

        activity('order')
            ->performedOn($order)
            ->withProperties(['status' => $data['status']])
            ->log("Order {$order->order_number} status changed to {$data['status']}");

        return response()->json($order->fresh());
    }
}
