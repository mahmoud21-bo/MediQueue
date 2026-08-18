<?php

namespace App\Policies;

use App\Models\DoctorProfile;
use App\Models\User;

class DoctorApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('platform_admin');
    }

    public function view(User $user, DoctorProfile $doctorProfile): bool
    {
        return $user->hasRole('platform_admin');
    }

    public function approve(User $user, DoctorProfile $doctorProfile): bool
    {
        return $user->hasRole('platform_admin');
    }

    public function reject(User $user, DoctorProfile $doctorProfile): bool
    {
        return $user->hasRole('platform_admin');
    }
}
