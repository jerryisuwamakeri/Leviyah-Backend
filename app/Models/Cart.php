<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_id', 'coupon_code', 'discount_amount'];

    protected $appends = ['subtotal', 'total', 'item_count'];

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2'];
    }

    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(CartItem::class); }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    public function getTotalAttribute(): float
    {
        return max(0, $this->subtotal - (float) $this->discount_amount);
    }

    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
