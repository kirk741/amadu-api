<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class EmotionLog extends Model
{
    use HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = "emotion_logs";
    protected $fillable = [
        'user_id',
        'emotion_id',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emotion()
    {
        return $this->belongsTo(Emotion::class);
    }
}
