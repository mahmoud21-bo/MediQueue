<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginPatientRequest;
use App\Http\Requests\Auth\RegisterPatientRequest;
use App\Services\Auth\AuthPayload;
use App\Services\Auth\LoginService;
use App\Services\Auth\PatientRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientAuthController extends ApiController
{
    public function register(RegisterPatientRequest $request, PatientRegistrationService $service): JsonResponse
    {
        return $this->success('Registration successful', $service->register($request->validated()), 201);
    }

    public function login(LoginPatientRequest $request, LoginService $service): JsonResponse
    {
        return $this->success('Login successful', $service->login(
            $request->validated('email'),
            $request->validated('password'),
            'patient',
        ));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success('Profile retrieved successfully', AuthPayload::make($request->user(), 'patient'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success('Logout successful');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success('Logged out from all devices successfully');
    }
}
