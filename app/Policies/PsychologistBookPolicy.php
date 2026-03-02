<?php

namespace App\Policies;

use App\Models\PsychologistBook;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PsychologistBookPolicy
{
    public function create(User $user): bool
    {
        return $user->role?->name === 'psychologist';
    }

    public function update(User $user, PsychologistBook $book): bool
    {
        return $user->id === $book->psychologist_id;
    }

    public function delete(User $user, PsychologistBook $book): bool
    {
        return $user->id === $book->psychologist_id;
    }
}
