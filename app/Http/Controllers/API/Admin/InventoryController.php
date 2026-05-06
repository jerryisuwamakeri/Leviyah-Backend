<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function report(Request $request)
    {
        $period = $request->period ?? 'month';

        $start = match ($period) {
            'week'  => now()->startOfWeek(),
            'year'  => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $groupFormat = match ($period) {
            'week'  => '%Y-%m-%d',
            'year'  => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $revenue = Transaction::where('status', 'success')
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '{$groupFormat}') as period, SUM(amount) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $expenses = Expense::where('expense_date', '>=', $start)
            ->selectRaw("DATE_FORMAT(expense_date, '{$groupFormat}') as period, SUM(amount) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $totalRevenue  = Transaction::where('status', 'success')->where('created_at', '>=', $start)->sum('amount');
        $totalExpenses = Expense::where('expense_date', '>=', $start)->sum('amount');
        $totalCOGS     = OrderItem::whereHas('order', fn($q) => $q->where('created_at', '>=', $start))->sum('total_price');

        $lowStock = Product::where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->where('stock_quantity', '>', 0)
            ->with('category:id,name')
            ->get(['id', 'name', 'stock_quantity', 'low_stock_threshold', 'category_id', 'thumbnail_url']);

        $outOfStock = Product::where('track_inventory', true)
            ->where('stock_quantity', 0)
            ->with('category:id,name')
            ->get(['id', 'name', 'stock_quantity', 'category_id']);

        $bestSellersByCategory = Category::with(['products' => function ($q) use ($start) {
            $q->withCount(['orderItems as units_sold' => function ($q) use ($start) {
                $q->whereHas('order', fn($o) => $o->where('created_at', '>=', $start));
            }])
            ->withSum(['orderItems as revenue' => function ($q) use ($start) {
                $q->whereHas('order', fn($o) => $o->where('created_at', '>=', $start));
            }], 'total_price')
            ->orderByDesc('units_sold')
            ->limit(5);
        }])->get(['id', 'name']);

        $inventoryValue = Product::where('is_active', true)
            ->selectRaw('SUM(stock_quantity * base_price) as value')
            ->value('value') ?? 0;

        return response()->json([
            'period'                  => $period,
            'revenue_chart'           => $revenue,
            'expenses_chart'          => $expenses,
            'summary' => [
                'total_revenue'       => (float) $totalRevenue,
                'total_expenses'      => (float) $totalExpenses,
                'gross_profit'        => (float) ($totalRevenue - $totalExpenses),
                'inventory_value'     => (float) $inventoryValue,
                'low_stock_count'     => $lowStock->count(),
                'out_of_stock_count'  => $outOfStock->count(),
            ],
            'low_stock'               => $lowStock,
            'out_of_stock'            => $outOfStock,
            'best_sellers_by_category'=> $bestSellersByCategory,
        ]);
    }

    public function lowStockAlerts()
    {
        $items = Product::where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->with('category:id,name')
            ->orderBy('stock_quantity')
            ->get(['id', 'name', 'slug', 'stock_quantity', 'low_stock_threshold', 'category_id', 'thumbnail']);

        return response()->json($items->each->append('thumbnail_url'));
    }

    public function barcodeSearch(Request $request)
    {
        $barcode = $request->validate(['barcode' => 'required|string'])['barcode'];

        $product = Product::where('barcode', $barcode)
            ->with(['category', 'variants', 'images'])
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json($product->append('thumbnail_url'));
    }
}
