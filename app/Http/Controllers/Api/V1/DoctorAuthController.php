<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginDoctorRequest;
use App\Http\Requests\Auth\RegisterDoctorRequest;
use App\Services\Auth\AuthPayload;
use App\Services\Auth\DoctorRegistrationService;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorAuthController extends ApiController
{
    public function register(RegisterDoctorRequest $request, DoctorRegistrationService $service): JsonResponse
    {
        return $this->success('Registration successful', $service->register($request->validated()), 201);
    }

    public function login(LoginDoctorRequest $request, LoginService $service): JsonResponse
    {
        return $this->success('Login successful', $service->login(
            $request->validated('email'),
            $request->validated('password'),
            'doctor',
            $request->validated('device_name', 'Doctor Device'),
        ));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success('Profile retrieved successfully', AuthPayload::make($request->user(), 'doctor'));
    }

    public function verificationStatus(Request $request): JsonResponse
    {
        return $this->success('Verification status retrieved successfully', [
            'verification_status' => $request->user()->doctorProfile->verification_status->value,
            'reviewed_at' => $request->user()->doctorProfile->reviewed_at,
            'approved_at' => $request->user()->doctorProfile->approved_at,
            'rejection_reason' => $request->user()->doctorProfile->rejection_reason,
        ]);
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
