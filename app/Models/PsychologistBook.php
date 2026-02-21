<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PsychologistBook extends Model
{
    protected $table = "psychologist_books";
    protected $fillable = [
            'psychologist_id',
            'title',
            'author',
            'comment'
    ];

    public function psychologist() {
        return $this->belongsTo(User::class);
    }

    public function media() {
        return $this->morphTo(Media::class, 'mediable');
    }
}
