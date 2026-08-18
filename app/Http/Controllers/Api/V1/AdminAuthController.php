<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginAdminRequest;
use App\Services\Auth\AuthPayload;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends ApiController
{
    public function login(LoginAdminRequest $request, LoginService $service): JsonResponse
    {
        return $this->success('Login successful', $service->login(
            $request->validated('email'),
            $request->validated('password'),
            'platform_admin',
            $request->validated('device_name', 'Admin Device'),
        ));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success('Profile retrieved successfully', AuthPayload::make(
            $request->user(),
            'platform_admin',
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success('Logout successful');
    }
}
