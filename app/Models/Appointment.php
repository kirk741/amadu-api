<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointments';
    protected $fillable = [
            'psychologist_id',
            'client_id',
            'scheduled_at',
            'duration_minutes',
    ];

    protected function casts()
    {
        return [
            'scheduled_at' => 'datetime'
        ];
    }

    public function psychologist() {
        return $this->belongsTo(User::class, 'psychologist_id');
    }

    public function client() {
        return $this->belongsTo(User::class, 'client_id');
    }
}
