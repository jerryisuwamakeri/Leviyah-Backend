<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    protected $fillable = [
        'name', 'type', 'description', 'percentage',
        'is_active', 'starts_at', 'ends_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_active'  => 'boolean',
            'starts_at'  => 'datetime',
            'ends_at'    => 'datetime',
        ];
    }

    public function creator() { return $this->belongsTo(Staff::class, 'created_by'); }

    public function isRunning(): bool
    {
        if (!$this->is_active) return false;
        $now = Carbon::now();
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at   && $now->gt($this->ends_at))   return false;
        return true;
    }

    /** Returns the highest active promotion percentage, or 0 */
    public static function activePercentage(): float
    {
        $promo = self::where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('percentage')
            ->first();

        return $promo ? (float) $promo->percentage : 0.0;
    }

    /** Apply promotion discount to a price */
    public static function applyTo(float $price): float
    {
        $pct = self::activePercentage();
        if ($pct <= 0) return $price;
        return round($price * (1 - $pct / 100), 2);
    }
}
