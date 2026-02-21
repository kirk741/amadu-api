<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaryType extends Model
{
    protected $table = "diary_types";
    protected $fillable = [
        'name'
    ];

    public function diaryEntries() {
        return $this->hasMany(DiaryEntry::class);
    }
}
