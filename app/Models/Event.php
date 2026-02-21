<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = "events";
    protected $fillable = [
        'psychologist_id',
        'title',
        'description',
        'event_date',
        'location'
    ];

    public function psychologist() {
        return $this->belongsTo(User::class, 'psychologist_id');
    }
}
