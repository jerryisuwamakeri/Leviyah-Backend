<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'reference', 'gateway', 'status',
        'amount', 'currency', 'gateway_response', 'channel', 'ip_address', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at'          => 'datetime',
        ];
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function user()  { return $this->belongsTo(User::class); }
}
