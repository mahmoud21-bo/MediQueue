<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AuthSessionResource;
use App\Services\Auth\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuthSessionController extends ApiController
{
    public function index(Request $request, SessionService $service): AnonymousResourceCollection
    {
        return AuthSessionResource::collection($service->forUser($request->user()))
            ->additional([
                'success' => true,
                'message' => 'Authenticated devices retrieved successfully',
            ]);
    }

    public function destroy(Request $request, int $tokenId, SessionService $service): JsonResponse
    {
        $token = $service->findOwnedToken($request->user(), $tokenId);
        Gate::authorize('delete', $token);
        $service->revoke($token);

        return $this->success('Device session revoked successfully');
    }
}
