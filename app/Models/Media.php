<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'collection',
        'file_path',
        'mime_type',
        'size',
        'file_name',
        'sort_order'
    ];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer'
    ];

    public function mediable()
    {
        return $this->morphTo();
    }
}
