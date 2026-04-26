<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'address_id', 'order_number', 'status', 'payment_status',
        'payment_method', 'subtotal', 'discount_amount', 'shipping_fee', 'total',
        'coupon_code', 'notes', 'tracking_number', 'shipping_address',
        'paid_at', 'shipped_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee'    => 'decimal:2',
            'total'           => 'decimal:2',
            'shipping_address'=> 'array',
            'paid_at'         => 'datetime',
            'shipped_at'      => 'datetime',
            'delivered_at'    => 'datetime',
        ];
    }

    public function user()         { return $this->belongsTo(User::class); }
    public function address()      { return $this->belongsTo(Address::class); }
    public function items()        { return $this->hasMany(OrderItem::class); }
    public function transaction()  { return $this->hasOne(Transaction::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            $order->order_number ??= 'LVY-' . strtoupper(uniqid());
        });
    }
}
