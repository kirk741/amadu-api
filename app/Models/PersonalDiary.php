<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PersonalDiary extends Model
{
    use HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = "personal_diaries";

    protected $fillable = [
        'user_id',
        'title',
        'content'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
