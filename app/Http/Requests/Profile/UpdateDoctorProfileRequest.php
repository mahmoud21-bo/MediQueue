<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Auth\ApiAuthRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorProfileRequest extends ApiAuthRequest
{
    public function rules(): array
    {
        $user = $this->user();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->getKey())],
            'phone' => ['sometimes', 'required', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users')->ignore($user->getKey())],
            'specialty_id' => ['sometimes', 'required', 'integer', 'exists:specialties,id'],
            'medical_license_number' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('doctor_profiles')->ignore($user->doctorProfile?->getKey()),
            ],
            'years_of_experience' => ['sometimes', 'required', 'integer', 'min:0', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
