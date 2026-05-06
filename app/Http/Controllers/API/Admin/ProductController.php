<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'primaryImage'])->withTrashed($request->boolean('trashed'));

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $results = $query->latest()->paginate($request->per_page ?? 20);
        $results->getCollection()->each->append('thumbnail_url');
        return response()->json($results);
    }

    public function store(Request $request)
    {
        foreach (['has_variants','is_active','is_featured','track_inventory'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        $data = $request->validate([
            'category_id'       => 'required|exists:categories,id',
            'name'              => 'required|string|max:255',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'sku'                  => 'nullable|string|unique:products',
            'barcode'              => 'nullable|string|unique:products',
            'low_stock_threshold'  => 'nullable|integer|min:0',
            'base_price'           => 'required|numeric|min:0',
            'sale_price'        => 'nullable|numeric|min:0',
            'stock_quantity'    => 'required|integer|min:0',
            'track_inventory'   => 'boolean',
            'has_variants'      => 'boolean',
            'product_type'      => 'in:simple,variable',
            'is_active'         => 'boolean',
            'is_featured'       => 'boolean',
            'weight'            => 'nullable|numeric',
            'tags'              => 'nullable|array',
            'variants'          => 'array',
            'variants.*.color'  => 'nullable|string',
            'variants.*.length' => 'nullable|string',
            'variants.*.price'  => 'required_with:variants|numeric',
            'variants.*.stock_quantity' => 'required_with:variants|integer',
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', $this->disk());
        }

        $product = DB::transaction(function () use ($data, $request) {
            $variants = $data['variants'] ?? [];
            unset($data['variants']);

            $product = Product::create($data);

            if (empty($product->barcode)) {
                $product->update(['barcode' => 'LVY' . str_pad($product->id, 6, '0', STR_PAD_LEFT)]);
            }

            foreach ($variants as $v) {
                $product->variants()->create($v);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', $this->disk());
                    ProductImage::create([
                        'product_id' => $product->id,
                        'url'        => $path,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $product;
        });

        return response()->json($product->load(['variants', 'images', 'category']), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['variants', 'images', 'category', 'reviews']));
    }

    public function update(Request $request, Product $product)
    {
        foreach (['has_variants','is_active','is_featured','track_inventory'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        $data = $request->validate([
            'category_id'       => 'sometimes|exists:categories,id',
            'name'              => 'sometimes|string|max:255',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'barcode'              => 'nullable|string|unique:products,barcode,'.request()->route('product')?->id,
            'low_stock_threshold'  => 'nullable|integer|min:0',
            'base_price'           => 'sometimes|numeric|min:0',
            'sale_price'           => 'nullable|numeric|min:0',
            'stock_quantity'       => 'sometimes|integer|min:0',
            'track_inventory'   => 'boolean',
            'has_variants'      => 'boolean',
            'is_active'         => 'boolean',
            'is_featured'       => 'boolean',
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('products', $this->disk());
        }

        $product->update($data);

        return response()->json($product->fresh()->load(['variants', 'images', 'category']));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted.']);
    }

    public function storeVariant(Request $request, Product $product)
    {
        $data = $request->validate([
            'color'          => 'nullable|string|max:100',
            'color_hex'      => 'nullable|string|max:7',
            'length'         => 'nullable|string|max:50',
            'size'           => 'nullable|string|max:50',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku'            => 'nullable|string|unique:product_variants',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('variants', $this->disk());
        }

        $variant = $product->variants()->create($data);

        return response()->json($variant, 201);
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant)
    {
        $data = $request->validate([
            'color'          => 'nullable|string|max:100',
            'color_hex'      => 'nullable|string|max:7',
            'length'         => 'nullable|string|max:50',
            'price'          => 'sometimes|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'is_active'      => 'boolean',
        ]);

        $variant->update($data);

        return response()->json($variant->fresh());
    }

    public function destroyVariant(Product $product, ProductVariant $variant)
    {
        $variant->delete();
        return response()->json(['message' => 'Variant deleted.']);
    }

    public function categories()
    {
        return response()->json(Category::with('children')->whereNull('parent_id')->get());
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);

        $base = Str::slug($data['name']);
        $slug = $base;
        $i    = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        $data['slug'] = $slug;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', $this->disk());
        }

        return response()->json(Category::create($data), 201);
    }
}
