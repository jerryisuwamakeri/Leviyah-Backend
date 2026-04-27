<?php

namespace App\Http\Controllers\API\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Promotion;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'primaryImage', 'variants' => fn($q) => $q->active()])
            ->active();

        if ($request->category) {
            $category = Category::where('slug', $request->category)->firstOrFail();
            $query->where('category_id', $category->id);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('short_description', 'like', "%{$request->search}%");
            });
        }

        if ($request->featured) {
            $query->featured();
        }

        if ($request->min_price) $query->where('base_price', '>=', $request->min_price);
        if ($request->max_price) $query->where('base_price', '<=', $request->max_price);

        $sort = $request->sort ?? 'newest';
        match ($sort) {
            'price_asc'  => $query->orderBy('base_price', 'asc'),
            'price_desc' => $query->orderBy('base_price', 'desc'),
            'popular'    => $query->orderBy('views', 'desc'),
            default      => $query->latest(),
        };

        $results = $query->paginate($request->per_page ?? 12);
        $results->getCollection()->each->append('thumbnail_url');
        $promoPct = Promotion::activePercentage();

        return response()->json(array_merge($results->toArray(), ['promo_percentage' => $promoPct]));
    }

    public function show(string $slug)
    {
        $product = Product::with([
            'category',
            'images',
            'variants' => fn($q) => $q->active(),
            'reviews'  => fn($q) => $q->approved()->with('user:id,name,avatar')->latest()->limit(10),
        ])->where('slug', $slug)->active()->firstOrFail();

        $product->increment('views');

        $related = Product::with(['primaryImage'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->active()->limit(4)->get();

        $promoPct = Promotion::activePercentage();

        return response()->json([
            'product'          => $product->append(['average_rating', 'effective_price', 'thumbnail_url']),
            'related'          => $related,
            'promo_percentage' => $promoPct,
        ]);
    }

    public function featured()
    {
        $products = Product::with(['primaryImage', 'category'])
            ->active()->featured()->latest()->limit(8)->get();

        return response()->json($products->each->append('thumbnail_url'));
    }

    public function categories()
    {
        $categories = Category::withCount(['products' => fn($q) => $q->active()])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }
}
