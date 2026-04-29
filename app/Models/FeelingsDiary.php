<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeelingsDiary extends Model
{
    use HasUlids, SoftDeletes;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'situation',
        'thoughts',
        'body_feelings',
        'feelings',
        'conclusion'
    ];

    protected $casts = [
        'situation' => 'encrypted',
        'thoughts' => 'encrypted',
        'body_feelings' => 'encrypted',
        'feelings' => 'encrypted',
        'conclusion' => 'encrypted',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
