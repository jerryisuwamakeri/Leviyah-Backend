<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'title', 'description', 'amount', 'category', 'expense_date', 'reference', 'staff_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function staff() { return $this->belongsTo(Staff::class); }
}
