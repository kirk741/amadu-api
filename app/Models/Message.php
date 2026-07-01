<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'messages';
    protected $fillable = [
        'content_type',
        'body',
        'read_at',
        'conversation_id',
        'sender_id'
    ];

    protected $casts = [
        'body' => 'encrypted',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
