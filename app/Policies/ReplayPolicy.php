<?php

namespace App\Policies;

use App\Models\Replay;
use App\Models\User;

class ReplayPolicy
{
    /**
     * Only the owner may change a replay (e.g. its visibility).
     */
    public function update(User $user, Replay $replay): bool
    {
        return $replay->isEditableBy($user);
    }

    public function delete(User $user, Replay $replay): bool
    {
        return $replay->isEditableBy($user);
    }
}
