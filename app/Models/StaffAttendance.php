<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    protected $fillable = [
        'staff_id', 'date', 'clock_in_at', 'clock_out_at',
        'hours_worked', 'method', 'ip_address', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'clock_in_at'  => 'datetime',
            'clock_out_at' => 'datetime',
            'hours_worked' => 'decimal:2',
        ];
    }

    public function staff() { return $this->belongsTo(Staff::class); }

    public function calculateHours(): void
    {
        if ($this->clock_in_at && $this->clock_out_at) {
            $this->hours_worked = round(
                $this->clock_out_at->diffInMinutes($this->clock_in_at) / 60, 2
            );
            $this->save();
        }
    }
}
