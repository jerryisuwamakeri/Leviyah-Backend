<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'color', 'color_hex', 'length',
        'size', 'price', 'sale_price', 'stock_quantity', 'image', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function product() { return $this->belongsTo(Product::class); }

    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->color, $this->length, $this->size]);
        return implode(' / ', $parts);
    }
}
