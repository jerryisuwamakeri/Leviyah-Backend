<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['order:id,order_number', 'user:id,name,email']);

        if ($request->status)   $query->where('status', $request->status);
        if ($request->gateway)  $query->where('gateway', $request->gateway);
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%"));
            });
        }
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        $summary = [
            'total'    => Transaction::where('status', 'success')->sum('amount'),
            'today'    => Transaction::where('status', 'success')->whereDate('created_at', today())->sum('amount'),
            'pending'  => Transaction::where('status', 'pending')->sum('amount'),
            'failed'   => Transaction::where('status', 'failed')->count(),
            'refunded' => Transaction::where('status', 'refunded')->sum('amount'),
        ];

        return response()->json([
            'transactions' => $query->latest()->paginate($request->per_page ?? 20),
            'summary'      => $summary,
        ]);
    }

    public function show(Transaction $transaction)
    {
        return response()->json($transaction->load(['order.items', 'user:id,name,email,phone']));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,success,failed,refunded',
            'notes'  => 'nullable|string|max:500',
        ]);

        $old = $transaction->status;
        $transaction->update([
            'status'  => $data['status'],
            'paid_at' => $data['status'] === 'success' && !$transaction->paid_at ? now() : $transaction->paid_at,
        ]);

        if ($data['status'] === 'success' && $old !== 'success') {
            $transaction->order?->update(['payment_status' => 'paid', 'paid_at' => now()]);
        } elseif ($data['status'] === 'refunded') {
            $transaction->order?->update(['payment_status' => 'refunded', 'status' => 'refunded']);
        }

        activity('transaction')
            ->causedBy($request->user())
            ->performedOn($transaction)
            ->withProperties(['old_status' => $old, 'new_status' => $data['status']])
            ->log("Transaction {$transaction->reference} status changed: {$old} → {$data['status']}");

        return response()->json($transaction->fresh()->load(['order:id,order_number', 'user:id,name,email']));
    }

    public function destroy(Transaction $transaction)
    {
        $ref = $transaction->reference;
        $transaction->delete();

        activity('transaction')
            ->causedBy(request()->user())
            ->withProperties(['reference' => $ref])
            ->log("Transaction {$ref} deleted");

        return response()->json(['message' => 'Transaction deleted.']);
    }
}
