<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function update(User $user, Schedule $schedule)
    {
        return $user->id === $schedule->user_id;
    }

    public function delete(User $user, Schedule $schedule)
    {
        return $user->id === $schedule->user_id;
    }
}
