<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasUlids;
    protected $table = 'schedules';

    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'is_booked'
    ];

    protected $casts = [
        'is_booked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments() {
        return $this->hasMany(Appointment::class);
    }
}
