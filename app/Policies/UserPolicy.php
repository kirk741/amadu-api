<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewMe(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, ['admin', 'psychologist', 'client']);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'client') {
            return $model->role->name === 'psychologist';
        }

        if ($user->role === 'psychologist') {
            return $model->role->name === 'psychologist' || $model->role->name === 'client';
        }

        return false;
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->role->name === 'admin';
    }

    public function manage(User $user)
    {
        return $user->role?->name === 'admin';
    }
}
