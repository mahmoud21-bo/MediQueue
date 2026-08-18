<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenPolicy
{
    public function delete(User $user, PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === $user->getMorphClass()
            && (int) $token->tokenable_id === (int) $user->getKey();
    }
}
