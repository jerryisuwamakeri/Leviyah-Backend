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
}
