<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'staff_id', 'subject', 'status', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user()         { return $this->belongsTo(User::class); }
    public function staff()        { return $this->belongsTo(Staff::class); }
    public function messages()     { return $this->hasMany(Message::class)->orderBy('created_at'); }
    public function lastMessage()  { return $this->hasOne(Message::class)->latest(); }
    public function unreadMessages() { return $this->hasMany(Message::class)->where('is_read', false); }
}
