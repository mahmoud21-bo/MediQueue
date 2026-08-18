<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('email')) {
            $normalized['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        if ($this->has('phone')) {
            $normalized['phone'] = self::normalizePhone((string) $this->input('phone'));
        }

        if ($this->has('device_name')) {
            $normalized['device_name'] = trim((string) $this->input('device_name'));
        }

        $this->merge($normalized);
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $hasInternationalPrefix = str_starts_with($phone, '+') || str_starts_with($phone, '00');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '00')) {
            $digits = substr($digits, 2);
        }

        return $hasInternationalPrefix ? '+'.$digits : $digits;
    }
}
