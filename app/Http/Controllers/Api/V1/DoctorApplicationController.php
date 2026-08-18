<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ApproveDoctorRequest;
use App\Http\Requests\ListDoctorApplicationsRequest;
use App\Http\Requests\RejectDoctorRequest;
use App\Http\Resources\DoctorApplicationResource;
use App\Models\DoctorProfile;
use App\Models\DoctorVerificationDocument;
use App\Services\DoctorVerification\DoctorApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoctorApplicationController extends ApiController
{
    public function statistics(DoctorApplicationService $service): JsonResponse
    {
        Gate::authorize('viewAny', DoctorProfile::class);

        return $this->success('Doctor application statistics retrieved successfully', $service->statistics());
    }

    public function index(
        ListDoctorApplicationsRequest $request,
        DoctorApplicationService $service,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', DoctorProfile::class);

        return DoctorApplicationResource::collection($service->paginate($request->validated()))
            ->additional([
                'success' => true,
                'message' => 'Doctor applications retrieved successfully',
            ]);
    }

    public function show(int $doctor, DoctorApplicationService $service): DoctorApplicationResource
    {
        $application = $service->findByDoctorId($doctor, true);
        Gate::authorize('view', $application);

        return DoctorApplicationResource::make($application)->additional([
            'success' => true,
            'message' => 'Doctor application retrieved successfully',
        ]);
    }

    public function approve(
        ApproveDoctorRequest $request,
        int $doctor,
        DoctorApplicationService $service,
    ): DoctorApplicationResource {
        $application = $service->findByDoctorId($doctor);
        Gate::authorize('approve', $application);

        $application = $service->approve(
            $application,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return DoctorApplicationResource::make($application)->additional([
            'success' => true,
            'message' => 'Doctor application approved successfully',
        ]);
    }

    public function reject(
        RejectDoctorRequest $request,
        int $doctor,
        DoctorApplicationService $service,
    ): DoctorApplicationResource {
        $application = $service->findByDoctorId($doctor);
        Gate::authorize('reject', $application);

        $application = $service->reject(
            $application,
            $request->user(),
            $request->validated('rejection_reason'),
            $request->ip(),
            $request->userAgent(),
        );

        return DoctorApplicationResource::make($application)->additional([
            'success' => true,
            'message' => 'Doctor application rejected successfully',
        ]);
    }

    public function document(
        Request $request,
        int $doctor,
        int $document,
        DoctorApplicationService $service,
    ): StreamedResponse {
        $application = $service->findByDoctorId($doctor);
        Gate::authorize('view', $application);

        $verificationDocument = DoctorVerificationDocument::query()
            ->whereKey($document)
            ->where('doctor_profile_id', $application->getKey())
            ->firstOrFail();

        abort_unless(Storage::disk($verificationDocument->disk)->exists($verificationDocument->path), 404);

        $headers = array_filter(['Content-Type' => $verificationDocument->mime_type]);

        if ($request->boolean('download')) {
            return Storage::disk($verificationDocument->disk)->download(
                $verificationDocument->path,
                $verificationDocument->original_name,
                $headers,
            );
        }

        return Storage::disk($verificationDocument->disk)->response(
            $verificationDocument->path,
            $verificationDocument->original_name,
            $headers,
            'inline',
        );
    }
}
