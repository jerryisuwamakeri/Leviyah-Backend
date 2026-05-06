<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Conversation;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $today     = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $stats = [
            'total_revenue'     => Transaction::where('status', 'success')->sum('amount'),
            'monthly_revenue'   => Transaction::where('status', 'success')->where('created_at', '>=', $thisMonth)->sum('amount'),
            'last_month_revenue'=> Transaction::where('status', 'success')->whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('amount'),
            'today_revenue'     => Transaction::where('status', 'success')->where('created_at', '>=', $today)->sum('amount'),
            'total_orders'      => Order::count(),
            'pending_orders'    => Order::where('status', 'pending')->count(),
            'confirmed_orders'  => Order::where('status', 'confirmed')->count(),
            'delivered_orders'  => Order::where('status', 'delivered')->count(),
            'cancelled_orders'  => Order::where('status', 'cancelled')->count(),
            'total_customers'   => User::count(),
            'new_customers'     => User::where('created_at', '>=', $thisMonth)->count(),
            'total_products'    => Product::count(),
            'low_stock'         => Product::where('track_inventory', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0)->count(),
            'out_of_stock'      => Product::where('stock_quantity', 0)->where('track_inventory', true)->count(),
            'total_expenses'    => Expense::where('expense_date', '>=', $thisMonth)->sum('amount'),
            'net_profit'        => Transaction::where('status', 'success')->where('created_at', '>=', $thisMonth)->sum('amount') - Expense::where('expense_date', '>=', $thisMonth)->sum('amount'),
            'open_chats'        => Conversation::where('status', 'open')->count(),
        ];

        $revenueChart = Transaction::where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $ordersChart = Order::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $ordersByStatus = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $topProducts = OrderItem::select('product_id', 'product_name')
            ->selectRaw('SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        $revenueByGateway = Transaction::where('status', 'success')
            ->selectRaw('gateway, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('gateway')
            ->get();

        $recentOrders = Order::with(['user:id,name,email', 'items'])
            ->latest()->limit(8)->get();

        $lowStockAlerts = Product::where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'stock_quantity', 'low_stock_threshold']);

        $bestSellersByCategory = Category::with(['products' => function ($q) {
            $q->withCount(['orderItems as units_sold'])
              ->withSum(['orderItems as revenue'], 'total_price')
              ->orderByDesc('units_sold')
              ->limit(3);
        }])->get(['id', 'name']);

        return response()->json([
            'stats'                   => $stats,
            'revenue_chart'           => $revenueChart,
            'orders_chart'            => $ordersChart,
            'orders_by_status'        => $ordersByStatus,
            'top_products'            => $topProducts,
            'revenue_by_gateway'      => $revenueByGateway,
            'recent_orders'           => $recentOrders,
            'low_stock_alerts'        => $lowStockAlerts,
            'best_sellers_by_category'=> $bestSellersByCategory,
        ]);
    }
}
