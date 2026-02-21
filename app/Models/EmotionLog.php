<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmotionLog extends Model
{
    protected $table = "emotion_logs";
    protected $fillable = [
        'user_id',
        'emotion_type'
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
