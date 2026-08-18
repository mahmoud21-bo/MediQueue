<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->is_active) {
            $user?->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'This account is inactive.',
            ], 403);
        }

        return $next($request);
    }
}
