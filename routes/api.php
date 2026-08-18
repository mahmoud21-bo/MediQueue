<?php

use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Http\Controllers\Api\V1\DoctorApplicationController;
use App\Http\Controllers\Api\V1\DoctorAuthController;
use App\Http\Controllers\Api\V1\DoctorProfileController;
use App\Http\Controllers\Api\V1\PatientAuthController;
use App\Http\Controllers\Api\V1\PatientProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json([
        'application' => config('app.name'),
        'status' => 'ok',
        'api_version' => 'v1',
    ]);
});

Route::prefix('v1')->group(function (): void {
    Route::prefix('admin')->group(function (): void {
        Route::post('auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:auth-login');

        Route::middleware(['auth:sanctum', 'role:platform_admin'])->group(function (): void {
            Route::post('auth/logout', [AdminAuthController::class, 'logout']);
            Route::get('auth/me', [AdminAuthController::class, 'me']);

            Route::prefix('doctor-applications')->group(function (): void {
                Route::get('statistics', [DoctorApplicationController::class, 'statistics']);
                Route::get('/', [DoctorApplicationController::class, 'index']);
                Route::get('{doctor}', [DoctorApplicationController::class, 'show'])->whereNumber('doctor');
                Route::patch('{doctor}/approve', [DoctorApplicationController::class, 'approve'])->whereNumber('doctor');
                Route::patch('{doctor}/reject', [DoctorApplicationController::class, 'reject'])->whereNumber('doctor');
                Route::get('{doctor}/documents/{document}', [DoctorApplicationController::class, 'document'])
                    ->whereNumber(['doctor', 'document'])
                    ->name('admin.doctor-applications.documents.show');
            });
        });
    });

    Route::prefix('patient/auth')->group(function (): void {
        Route::post('register', [PatientAuthController::class, 'register'])->middleware('throttle:auth-register');
        Route::post('login', [PatientAuthController::class, 'login'])->middleware('throttle:auth-login');

        Route::middleware(['auth:sanctum', 'role:patient'])->group(function (): void {
            Route::post('logout', [PatientAuthController::class, 'logout']);
            Route::post('logout-all', [PatientAuthController::class, 'logoutAll']);
            Route::get('me', [PatientAuthController::class, 'me']);
        });
    });

    Route::patch('patient/profile', [PatientProfileController::class, 'update'])
        ->middleware(['auth:sanctum', 'role:patient']);

    Route::prefix('doctor/auth')->group(function (): void {
        Route::post('register', [DoctorAuthController::class, 'register'])->middleware('throttle:auth-register');
        Route::post('login', [DoctorAuthController::class, 'login'])->middleware('throttle:auth-login');

        Route::middleware(['auth:sanctum', 'role:doctor'])->group(function (): void {
            Route::post('logout', [DoctorAuthController::class, 'logout']);
            Route::post('logout-all', [DoctorAuthController::class, 'logoutAll']);
            Route::get('me', [DoctorAuthController::class, 'me']);
            Route::get('verification-status', [DoctorAuthController::class, 'verificationStatus']);
        });
    });

    Route::patch('doctor/profile', [DoctorProfileController::class, 'update'])
        ->middleware(['auth:sanctum', 'role:doctor']);
});
