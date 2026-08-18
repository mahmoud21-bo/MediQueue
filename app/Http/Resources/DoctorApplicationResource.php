<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'doctor_id' => $user->id,
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_image' => null,
            'specialty' => [
                'id' => $this->specialty->id,
                'name' => $this->specialty->name,
                'slug' => $this->specialty->slug,
            ],
            'medical_license_number' => $this->medical_license_number,
            'years_of_experience' => $this->years_of_experience,
            'biography' => $this->bio,
            'verification_status' => $this->verification_status->value,
            'application_submitted_at' => $this->application_submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'account' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'profile' => [
                'id' => $this->id,
                'medical_license_number' => $this->medical_license_number,
                'years_of_experience' => $this->years_of_experience,
                'bio' => $this->bio,
            ],
            'clinic' => null,
            'verification_documents' => $this->whenLoaded('verificationDocuments', fn () => $this->verificationDocuments->map(fn ($document): array => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'authorized_endpoint' => route('admin.doctor-applications.documents.show', [
                    'doctor' => $user->id,
                    'document' => $document->id,
                ], false),
            ])),
            'review' => $this->whenLoaded('reviewer', fn (): ?array => $this->reviewer ? [
                'reviewed_by' => $this->reviewer->id,
                'reviewer_name' => trim($this->reviewer->first_name.' '.$this->reviewer->last_name),
                'reviewed_at' => $this->reviewed_at?->toISOString(),
            ] : null),
            'review_history' => $this->whenLoaded('verificationAudits', fn () => $this->verificationAudits->map(fn ($audit): array => [
                'id' => $audit->id,
                'from_status' => $audit->from_status,
                'to_status' => $audit->to_status,
                'rejection_reason' => $audit->rejection_reason,
                'reviewed_by' => $audit->reviewed_by,
                'reviewer_name' => $audit->reviewer
                    ? trim($audit->reviewer->first_name.' '.$audit->reviewer->last_name)
                    : null,
                'reviewed_at' => $audit->created_at?->toISOString(),
            ])),
        ];
    }
}
