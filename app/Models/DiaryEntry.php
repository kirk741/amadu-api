<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaryEntry extends Model
{
    protected $table = "diary_entries";
    protected $fillable = [
        'user_id',
        'type_id',
        'title',
        'content',
        'date'
    ];

    protected function casts()
    {
        return [
            'date' => 'date'
        ];
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(DiaryType::class);
    }
}
