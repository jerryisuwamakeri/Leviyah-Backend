<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'minimum_order',
        'maximum_discount', 'usage_limit', 'used_count', 'is_active',
        'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value'            => 'decimal:2',
            'minimum_order'    => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'is_active'        => 'boolean',
            'starts_at'        => 'datetime',
            'expires_at'       => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        if ($this->starts_at && Carbon::now()->lt($this->starts_at)) return false;
        if ($this->expires_at && Carbon::now()->gt($this->expires_at)) return false;
        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < (float) $this->minimum_order) return 0;

        $discount = $this->type === 'percentage'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->maximum_discount) {
            $discount = min($discount, (float) $this->maximum_discount);
        }

        return round($discount, 2);
    }
}
