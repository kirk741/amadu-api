<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPhone extends Model
{
    protected $table = "support_phones";
    protected $fillable = [
        'phone',
        'title',
        'description'
    ];

    public function scopeSearch($query, $term)
    {
        return $query->where('title', 'like', "%$term%")
            ->orWhere('phone', 'like', "%$term%")
            ->orWhere('description', 'like', "%$term%");
    }
}
