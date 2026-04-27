<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'short_description', 'description',
        'sku', 'base_price', 'sale_price', 'stock_quantity', 'track_inventory',
        'has_variants', 'product_type', 'thumbnail', 'is_active', 'is_featured',
        'weight', 'tags',
    ];

    protected $appends = ['thumbnail_url'];

    protected function casts(): array
    {
        return [
            'base_price'       => 'decimal:2',
            'sale_price'       => 'decimal:2',
            'is_active'        => 'boolean',
            'is_featured'      => 'boolean',
            'has_variants'     => 'boolean',
            'track_inventory'  => 'boolean',
            'tags'             => 'array',
        ];
    }

    public function category()       { return $this->belongsTo(Category::class); }
    public function variants()       { return $this->hasMany(ProductVariant::class); }
    public function images()         { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function primaryImage()   { return $this->hasOne(ProductImage::class)->where('is_primary', true); }
    public function reviews()        { return $this->hasMany(Review::class); }
    public function orderItems()     { return $this->hasMany(OrderItem::class); }
    public function cartItems()      { return $this->hasMany(CartItem::class); }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail) return null;
        if (str_starts_with($this->thumbnail, 'http')) return $this->thumbnail;
        $disk = config('filesystems.default') === 'local' ? 'public' : config('filesystems.default');
        return \Storage::disk($disk)->url($this->thumbnail);
    }

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->base_price);
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) $this->reviews()->where('is_approved', true)->avg('rating') ?? 0;
    }

    public function scopeActive($query)   { return $query->where('is_active', true); }
    public function scopeFeatured($query) { return $query->where('is_featured', true); }
}
