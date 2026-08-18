<?php

namespace App\Http\Requests\Auth;

class LoginPatientRequest extends ApiAuthRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
