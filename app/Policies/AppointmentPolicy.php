<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user,)
    {
        return $user->role && in_array($user->role->name, ['client', 'psychologist']);
    }

    public function create(User $user)
    {
        return $user->role->name === 'client';
    }

    public function view(User $user, Appointment $appointment)
    {
        return $user->id === $appointment->client_id || $user->id === $appointment->psychologist_id;
    }

    public function update(User $user, Appointment $appointment)
    {
        return $user->id === $appointment->client_id || $user->id === $appointment->psychologist_id;
    }

    public function delete(User $user, Appointment $appointment)
    {
        return $user->role->name === 'admin' || $user->id === $appointment->client_id || $user->id === $appointment->psychologist_id;
    }
}
