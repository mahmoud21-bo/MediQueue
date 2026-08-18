<?php

namespace App\Services\Auth;

use App\Models\User;

class AuthPayload
{
    public static function make(User $user, string $role, ?string $plainTextToken = null): array
    {
        $profile = match ($role) {
            'doctor' => $user->doctorProfile()->with('specialty')->firstOrFail(),
            'patient' => $user->patientProfile()->firstOrFail(),
            default => null,
        };

        $verificationStatus = $role === 'doctor'
            ? $profile->verification_status->value
            : null;

        $data = [
            'user' => $user->fresh(),
            'profile' => $profile,
            'role' => $role,
            'verification_status' => $verificationStatus,
        ];

        if ($plainTextToken !== null) {
            $data['token'] = $plainTextToken;
            $data['token_type'] = 'Bearer';
        }

        return $data;
    }
}
