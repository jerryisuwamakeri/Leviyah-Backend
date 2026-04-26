<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Staff extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar',
        'employee_id', 'department', 'position', 'hourly_rate',
        'qr_code', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password'      => 'hashed',
            'hourly_rate'   => 'decimal:2',
        ];
    }

    public function attendances()   { return $this->hasMany(StaffAttendance::class); }
    public function conversations() { return $this->hasMany(Conversation::class); }
    public function todayAttendance()
    {
        return $this->hasOne(StaffAttendance::class)->whereDate('date', today());
    }
}
