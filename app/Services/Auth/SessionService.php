<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class SessionService
{
    /** @return Collection<int, PersonalAccessToken> */
    public function forUser(User $user): Collection
    {
        return $user->tokens()->latest()->get();
    }

    public function findOwnedToken(User $user, int $tokenId): PersonalAccessToken
    {
        return $user->tokens()->whereKey($tokenId)->firstOrFail();
    }

    public function revoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
