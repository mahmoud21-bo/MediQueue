<?php

namespace App\Http\Requests\Auth;

class LoginDoctorRequest extends ApiAuthRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
        ];
    }
}
