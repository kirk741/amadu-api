<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emotion extends Model
{
    protected $table = "emotions";
    protected $fillable = [
        'name'
    ];

    public function emotionLogs() {
        return $this->hasMany(EmotionLog::class);
    }

    public function media() {
        return $this->morphMany(Media::class, 'mediable');
    }
}
