<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPhone extends Model
{
    protected $table = "support_phones";
    protected $fillable = [
        'phone',
        'title',
        'description',
    ];
}
