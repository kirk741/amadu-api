<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'name',
        'birth_date',
        'bio',
        'role_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'settings' => 'array',
            'birth_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (!$user->role_id) {
                $clientRole = Role::where('name', 'client')->first();
                if ($clientRole) {
                    $user->role_id = $clientRole->id;
                }
            }
        });
    }

    public function psychologistBooks()
    {
        return $this->hasMany(PsychologistBook::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function psychologistAppointment()
    {
        return $this->hasMany(Appointment::class, 'psychologist_id');
    }

    public function clientAppointment()
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    public function psychologistConversation()
    {
        return $this->hasMany(Conversation::class, 'psychologist_id');
    }

    public function clientConversation()
    {
        return $this->hasMany(Conversation::class, 'client_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function emotionLogs()
    {
        return $this->hasMany(EmotionLog::class);
    }

    public function personalDiaries()
    {
        return $this->hasMany(PersonalDiary::class);
    }

    public function feelingsDiaries()
    {
        return $this->hasMany(FeelingsDiary::class);
    }

    public function foodDiaries()
    {
        return $this->hasMany(FoodDiary::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
