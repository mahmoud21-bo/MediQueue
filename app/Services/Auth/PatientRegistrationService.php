<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class PatientRegistrationService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create(Arr::only($data, [
                'first_name', 'last_name', 'email', 'phone', 'password',
            ]));

            Role::findOrCreate('patient', 'web');
            $user->assignRole('patient');
            $user->patientProfile()->create(Arr::only($data, ['date_of_birth', 'gender']));

            $token = $user->createToken($data['device_name'] ?? 'Patient Device')->plainTextToken;

            return AuthPayload::make($user, 'patient', $token);
        });
    }
}
