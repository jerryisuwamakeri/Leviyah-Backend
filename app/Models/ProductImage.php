<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'url', 'alt', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function product() { return $this->belongsTo(Product::class); }

    public function getUrlAttribute($value): ?string
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        try {
            $disk = config('filesystems.default') === 'local' ? 'public' : config('filesystems.default');
            return \Storage::disk($disk)->url($value);
        } catch (\Throwable) {
            return \Storage::disk('public')->url($value);
        }
    }
}
