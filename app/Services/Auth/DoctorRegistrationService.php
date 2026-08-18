<?php

namespace App\Services\Auth;

use App\Enums\DoctorVerificationStatus;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DoctorRegistrationService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create(Arr::only($data, [
                'first_name', 'last_name', 'email', 'phone', 'password',
            ]));

            Role::findOrCreate('doctor', 'web');
            $user->assignRole('doctor');
            $user->doctorProfile()->create([
                ...Arr::only($data, [
                    'specialty_id', 'medical_license_number', 'years_of_experience', 'bio',
                ]),
                'verification_status' => DoctorVerificationStatus::Pending,
                'application_submitted_at' => now(),
            ]);

            $token = $user->createToken($data['device_name'] ?? 'Doctor Device')->plainTextToken;

            return AuthPayload::make($user, 'doctor', $token);
        });
    }
}
