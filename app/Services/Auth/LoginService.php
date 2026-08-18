<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginService
{
    public function login(string $email, string $password, string $expectedRole): array
    {
        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password) || $user->trashed() || ! $user->hasRole($expectedRole)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($expectedRole.'-api')->plainTextToken;

        return AuthPayload::make($user, $expectedRole, $token);
    }
}
