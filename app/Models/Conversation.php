<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';
    protected $fillable = [
            'type',
            'client_id',
            'psychologist_id'
    ];

    protected function casts()
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    public function scopeWithUserName($query, $name) {
        return $query->whereHas('client', fn($q) => $q->where('name', 'like', "%$name%"))
        ->orWhereHas('psychologist', fn($q) => $q->where('name', 'like', "%$name%"));
    }

    public function messages() {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function client() {
        return $this->belongsTo(User::class, 'client_id')->withDefault();
    }

    public function psychologist() {
        return $this->belongsTo(User::class, 'psychologist_id')->withDefault();
    }
}
