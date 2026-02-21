<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'birth_date',
        'bio'
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role() {
        return $this->belongsTo(Role::class);
    }

    public function emotionLogs() {
        return $this->hasMany(EmotionLog::class);
    }

    public function messages() {
        return $this->hasMany(Message::class);
    }

    public function clientConversations() {
        return $this->hasMany(Conversation::class, 'client_id');
    }

    public function psychologistConversations() {
        return $this->hasMany(Conversation::class, 'psychologist_id');
    }

    public function psychologistBooks() {
        return $this->hasMany(PsychologistBook::class);
    }

    public function events() {
        return $this->hasMany(Event::class);
    }

    public function appointments() {
        return $this->hasMany(Appointment::class);
    }

    public function notifications() {
        return $this->hasMany(Notification::class);
    }

    public function diaryEntries() {
        return $this->hasMany(DiaryEntry::class);
    }

    public function media() {
        return $this->morphMany(Media::class, 'mediable');
    }
}
