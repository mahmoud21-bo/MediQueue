<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Profile\UpdatePatientProfileRequest;
use App\Services\Auth\AuthPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PatientProfileController extends ApiController
{
    public function update(UpdatePatientProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->update(Arr::only($data, ['first_name', 'last_name', 'email', 'phone']));
            $user->patientProfile->update(Arr::only($data, ['date_of_birth', 'gender']));
        });

        return $this->success('Profile updated successfully', AuthPayload::make($user, 'patient'));
    }
}
