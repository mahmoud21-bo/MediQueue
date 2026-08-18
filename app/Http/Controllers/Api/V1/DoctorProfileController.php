<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Profile\UpdateDoctorProfileRequest;
use App\Services\Auth\AuthPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DoctorProfileController extends ApiController
{
    public function update(UpdateDoctorProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->update(Arr::only($data, ['first_name', 'last_name', 'email', 'phone']));
            $user->doctorProfile->update(Arr::only($data, [
                'specialty_id', 'medical_license_number', 'years_of_experience', 'bio',
            ]));
        });

        return $this->success('Profile updated successfully', AuthPayload::make($user, 'doctor'));
    }
}
