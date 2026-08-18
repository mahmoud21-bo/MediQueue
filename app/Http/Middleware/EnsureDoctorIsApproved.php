<?php

namespace App\Http\Middleware;

use App\Enums\DoctorVerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole('doctor') || $user->doctorProfile?->verification_status !== DoctorVerificationStatus::Approved) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor account approval is required.',
            ], 403);
        }

        return $next($request);
    }
}
