<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

class RegisterDoctorRequest extends ApiAuthRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^\+?[0-9]{7,15}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'medical_license_number' => ['required', 'string', 'max:100', 'unique:doctor_profiles,medical_license_number'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:80'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
