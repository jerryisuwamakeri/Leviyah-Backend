<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'order_id', 'rating', 'title', 'body', 'is_approved',
    ];

    protected function casts(): array
    {
        return ['is_approved' => 'boolean'];
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function order()   { return $this->belongsTo(Order::class); }

    public function scopeApproved($query) { return $query->where('is_approved', true); }
}
