<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Auth\ApiAuthRequest;
use Illuminate\Validation\Rule;

class UpdatePatientProfileRequest extends ApiAuthRequest
{
    public function rules(): array
    {
        $userId = $this->user()->getKey();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($userId)],
            'phone' => ['sometimes', 'required', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users')->ignore($userId)],
            'date_of_birth' => ['sometimes', 'required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['sometimes', 'required', 'string', 'in:male,female,other'],
        ];
    }
}
