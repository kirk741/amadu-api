<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role?->name, ['admin', 'psychologist']);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id || $user->role?->name === 'admin';
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id || $user->role?->name === 'admin';
    }
}
