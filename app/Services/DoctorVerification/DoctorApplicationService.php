<?php

namespace App\Services\DoctorVerification;

use App\Enums\DoctorVerificationStatus;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Notifications\DoctorApprovedNotification;
use App\Notifications\DoctorRejectedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DoctorApplicationService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = DoctorProfile::query()->with(['user', 'specialty']);

        if (filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes(trim($filters['search']), '%_\\').'%';
            $query->where(function ($query) use ($term): void {
                $query->where('medical_license_number', 'like', $term)
                    ->orWhereHas('user', function ($query) use ($term): void {
                        $query->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term])
                            ->orWhere('email', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    });
            });
        }

        $query
            ->when($filters['verification_status'] ?? null, fn ($query, $status) => $query->where('verification_status', $status))
            ->when($filters['specialty_id'] ?? null, fn ($query, $specialtyId) => $query->where('specialty_id', $specialtyId))
            ->when($filters['application_date'] ?? null, fn ($query, $date) => $query->whereDate('application_submitted_at', $date));

        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy('application_submitted_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function statistics(): array
    {
        $statistics = DoctorProfile::query()
            ->selectRaw(
                'COUNT(*) AS total, SUM(verification_status = ?) AS pending, SUM(verification_status = ?) AS approved, SUM(verification_status = ?) AS rejected',
                ['pending', 'approved', 'rejected'],
            )->firstOrFail();

        return [
            'total' => (int) $statistics->total,
            'pending' => (int) $statistics->pending,
            'approved' => (int) $statistics->approved,
            'rejected' => (int) $statistics->rejected,
        ];
    }

    public function findByDoctorId(int $doctorId, bool $withDetails = false): DoctorProfile
    {
        $relations = ['user', 'specialty'];

        if ($withDetails) {
            $relations = [
                ...$relations,
                'reviewer',
                'verificationDocuments',
                'verificationAudits' => fn ($query) => $query->with('reviewer')->latest(),
            ];
        }

        return DoctorProfile::query()
            ->where('user_id', $doctorId)
            ->with($relations)
            ->firstOrFail();
    }

    public function approve(
        DoctorProfile $application,
        User $admin,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): DoctorProfile {
        return $this->review(
            application: $application,
            admin: $admin,
            status: DoctorVerificationStatus::Approved,
            rejectionReason: null,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    public function reject(
        DoctorProfile $application,
        User $admin,
        string $rejectionReason,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): DoctorProfile {
        return $this->review(
            application: $application,
            admin: $admin,
            status: DoctorVerificationStatus::Rejected,
            rejectionReason: $rejectionReason,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    private function review(
        DoctorProfile $application,
        User $admin,
        DoctorVerificationStatus $status,
        ?string $rejectionReason,
        ?string $ipAddress,
        ?string $userAgent,
    ): DoctorProfile {
        $doctorId = $application->user_id;

        DB::transaction(function () use (
            $application,
            $admin,
            $status,
            $rejectionReason,
            $ipAddress,
            $userAgent,
        ): void {
            $lockedApplication = DoctorProfile::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->verification_status !== DoctorVerificationStatus::Pending) {
                throw new ConflictHttpException('This doctor application has already been reviewed.');
            }

            $reviewedAt = now();
            $lockedApplication->update([
                'verification_status' => $status,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => $reviewedAt,
                'approved_at' => $status === DoctorVerificationStatus::Approved ? $reviewedAt : null,
                'rejection_reason' => $rejectionReason,
            ]);

            $lockedApplication->verificationAudits()->create([
                'reviewed_by' => $admin->getKey(),
                'from_status' => DoctorVerificationStatus::Pending->value,
                'to_status' => $status->value,
                'rejection_reason' => $rejectionReason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            $notification = $status === DoctorVerificationStatus::Approved
                ? new DoctorApprovedNotification
                : new DoctorRejectedNotification((string) $rejectionReason);

            $lockedApplication->user->notify($notification);
        });

        return $this->findByDoctorId($doctorId, true);
    }
}
