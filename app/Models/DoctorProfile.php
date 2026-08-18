<?php

namespace App\Models;

use App\Enums\DoctorVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'specialty_id', 'medical_license_number', 'years_of_experience', 'bio',
    'verification_status', 'reviewed_at', 'reviewed_by', 'approved_at', 'rejection_reason',
    'application_submitted_at',
])]
class DoctorProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'verification_status' => DoctorVerificationStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'application_submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verificationAudits(): HasMany
    {
        return $this->hasMany(DoctorVerificationAudit::class);
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(DoctorVerificationDocument::class);
    }
}
