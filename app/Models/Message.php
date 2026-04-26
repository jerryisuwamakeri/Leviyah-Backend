<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_id',
        'body', 'attachment', 'is_read', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function sender()       { return $this->morphTo(); }
}
