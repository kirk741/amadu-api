<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BaseDiaryPolicy
{
    public function viewAny(User $user) {
        return $user->role && $user->role->name === 'client';
    }

    public function create(User $user) {
        return $user->role && $user->role->name === 'client';
    }

    public function view(User $user, Model $diary) {
        return $user->id === $diary->user_id;
    }

    public function update(User $user, Model $diary) {
        return $user->id === $diary->user_id;
    }

    public function softDelete(User $user, Model $diary) {
        return $user->id === $diary->user_id;
    }

    public function delete(User $user, Model $diary) {
        return $user->id === $diary->user_id;
    }

    public function restore(User $user, Model $diary) {
        return $user->id === $diary->user_id;
    }
}
