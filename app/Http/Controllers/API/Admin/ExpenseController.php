<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $applyFilters = function ($q) use ($request) {
            if ($request->category) $q->where('category', $request->category);
            if ($request->from)     $q->where('expense_date', '>=', $request->from);
            if ($request->to)       $q->where('expense_date', '<=', $request->to);
            if ($request->period) {
                match ($request->period) {
                    'week'  => $q->where('expense_date', '>=', now()->startOfWeek()),
                    'month' => $q->where('expense_date', '>=', now()->startOfMonth()),
                    'year'  => $q->where('expense_date', '>=', now()->startOfYear()),
                    default => null,
                };
            }
        };

        $listQuery  = Expense::with('staff:id,name')->latest('expense_date');
        $statsQuery = Expense::query();
        $applyFilters($listQuery);
        $applyFilters($statsQuery);

        $expenses       = $listQuery->paginate($request->per_page ?? 20);
        $total          = (clone $statsQuery)->sum('amount');
        $categoryTotals = (clone $statsQuery)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'expenses'        => $expenses,
            'total'           => (float) $total,
            'category_totals' => $categoryTotals,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'amount'       => 'required|numeric|min:0',
            'category'     => 'required|string|max:100',
            'expense_date' => 'required|date',
            'reference'    => 'nullable|string|max:100',
        ]);

        $data['staff_id'] = $request->user()->id;

        return response()->json(Expense::create($data), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'amount'       => 'sometimes|numeric|min:0',
            'category'     => 'sometimes|string|max:100',
            'expense_date' => 'sometimes|date',
            'reference'    => 'nullable|string|max:100',
        ]);

        $expense->update($data);
        return response()->json($expense->fresh());
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return response()->json(['message' => 'Expense deleted.']);
    }

    public function categories()
    {
        return response()->json([
            'categories' => ['general', 'rent', 'utilities', 'salaries', 'supplies', 'marketing', 'shipping', 'maintenance', 'other'],
        ]);
    }
}
