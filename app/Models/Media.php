<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';
    protected $fillable = [
        'mediable_id',
        'mediable_type',
        'file_path',
        'mime_type',
        'size'
    ];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    public function mediable()
    {
        return $this->morphTo();
    }
}
