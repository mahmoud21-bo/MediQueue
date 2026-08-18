<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'doctor_profile_id', 'document_type', 'original_name', 'path', 'mime_type', 'size',
])]
#[Hidden(['disk', 'path'])]
class DoctorVerificationDocument extends Model
{
    protected $attributes = ['disk' => 'local'];

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class);
    }
}
