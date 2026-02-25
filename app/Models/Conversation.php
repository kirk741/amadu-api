<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'conversations';
    protected $fillable = [
        'type',
        'client_id',
        'psychologist_id'
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function psychologist()
    {
        return $this->belongsTo(User::class, 'psychologist_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
