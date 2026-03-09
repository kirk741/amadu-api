<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'appointments';
    protected $fillable = [
        'schedule_id',
        'status',
        'notification_id',
        'psychologist_id',
        'client_id'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function psychologist()
    {
        return $this->belongsTo(User::class, 'psychologist_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
}
