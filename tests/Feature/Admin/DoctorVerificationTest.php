<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorVerificationStatus;
use App\Http\Middleware\EnsureDoctorIsApproved;
use App\Models\DoctorProfile;
use App\Models\DoctorVerificationDocument;
use App\Models\Specialty;
use App\Models\User;
use App\Notifications\DoctorApprovedNotification;
use App\Notifications\DoctorRejectedNotification;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Valid#Pass1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, SpecialtySeeder::class]);
    }

    public function test_admin_can_login_and_no_public_registration_route_exists(): void
    {
        config(['admin' => [
            'first_name' => 'Seeded',
            'last_name' => 'Admin',
            'email' => 'admin@example.test',
            'phone' => '+1 (555) 000-0001',
            'password' => 'Example#Admin1',
        ]]);
        $this->seed(AdminSeeder::class);
        $admin = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertTrue($admin->hasRole('platform_admin'));
        $this->assertTrue(Hash::check('Example#Admin1', $admin->password));
        $this->assertSame('+15550000001', $admin->phone);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => strtoupper($admin->email),
            'password' => 'Example#Admin1',
        ])->assertOk()
            ->assertJsonPath('data.role', 'platform_admin')
            ->assertJsonPath('data.profile', null)
            ->assertJsonStructure(['data' => ['token', 'token_type']]);

        $this->postJson('/api/v1/admin/auth/register', [])->assertNotFound();
    }

    public function test_non_admin_cannot_access_doctor_applications(): void
    {
        $doctor = $this->createDoctor();
        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/admin/doctor-applications')->assertForbidden();
    }

    public function test_admin_can_list_pending_doctors_and_view_statistics(): void
    {
        $this->createDoctor(['first_name' => 'Pending'], [
            'verification_status' => DoctorVerificationStatus::Pending,
        ]);
        $this->createDoctor(['first_name' => 'Approved'], [
            'verification_status' => DoctorVerificationStatus::Approved,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/doctor-applications?verification_status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Pending Doctor')
            ->assertJsonPath('data.0.verification_status', 'pending')
            ->assertJsonStructure(['data' => [[
                'doctor_id', 'full_name', 'email', 'phone', 'profile_image', 'specialty',
                'medical_license_number', 'years_of_experience', 'verification_status',
                'application_submitted_at', 'reviewed_at', 'rejection_reason',
            ]], 'links', 'meta']);

        $this->getJson('/api/v1/admin/doctor-applications/statistics')
            ->assertOk()
            ->assertJsonPath('data', [
                'total' => 2,
                'pending' => 1,
                'approved' => 1,
                'rejected' => 0,
            ]);
    }

    public function test_search_filters_application_date_and_oldest_sort_are_supported(): void
    {
        $otherSpecialty = Specialty::create(['name' => 'Radiology', 'slug' => 'radiology']);
        $matching = $this->createDoctor([
            'first_name' => 'Nadia',
            'email' => 'nadia@example.com',
            'phone' => '+15551110001',
        ], [
            'specialty_id' => $otherSpecialty->id,
            'medical_license_number' => 'RAD-SEARCH-1',
            'application_submitted_at' => '2026-08-10 09:00:00',
        ]);
        $this->createDoctor([
            'first_name' => 'Other',
            'email' => 'other-doctor@example.com',
            'phone' => '+15551110002',
        ], [
            'verification_status' => DoctorVerificationStatus::Rejected,
            'medical_license_number' => 'OTHER-2',
            'application_submitted_at' => '2026-08-11 09:00:00',
        ]);
        $this->actingAsAdmin();

        $query = http_build_query([
            'search' => 'RAD-SEARCH',
            'verification_status' => 'pending',
            'specialty_id' => $otherSpecialty->id,
            'application_date' => '2026-08-10',
            'sort' => 'oldest',
        ]);

        $this->getJson('/api/v1/admin/doctor-applications?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.doctor_id', $matching->id);
    }

    public function test_admin_can_view_application_details_without_public_document_paths(): void
    {
        Storage::fake('local');
        $doctor = $this->createDoctor();
        Storage::disk('local')->put('doctor-verification/license.pdf', 'private-pdf');
        $document = DoctorVerificationDocument::create([
            'doctor_profile_id' => $doctor->doctorProfile->id,
            'document_type' => 'medical_license',
            'original_name' => 'license.pdf',
            'disk' => 'local',
            'path' => 'doctor-verification/license.pdf',
            'mime_type' => 'application/pdf',
            'size' => 11,
        ]);
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/doctor-applications/'.$doctor->id)
            ->assertOk()
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonPath('data.clinic', null)
            ->assertJsonPath('data.verification_documents.0.id', $document->id)
            ->assertJsonPath(
                'data.verification_documents.0.authorized_endpoint',
                "/api/v1/admin/doctor-applications/{$doctor->id}/documents/{$document->id}",
            );

        $this->assertStringNotContainsString(
            'doctor-verification/license.pdf',
            $response->getContent(),
        );
    }

    public function test_admin_can_approve_pending_doctor_with_audit_and_notification(): void
    {
        $doctor = $this->createDoctor();
        $admin = $this->actingAsAdmin();

        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'approved')
            ->assertJsonPath('data.review.reviewed_by', $admin->id);

        $profile = $doctor->doctorProfile->fresh();
        $this->assertSame(DoctorVerificationStatus::Approved, $profile->verification_status);
        $this->assertSame($admin->id, $profile->reviewed_by);
        $this->assertNotNull($profile->reviewed_at);
        $this->assertNotNull($profile->approved_at);
        $this->assertNull($profile->rejection_reason);
        $this->assertDatabaseHas('doctor_verification_audits', [
            'doctor_profile_id' => $profile->id,
            'reviewed_by' => $admin->id,
            'from_status' => 'pending',
            'to_status' => 'approved',
        ]);

        $notification = $doctor->notifications()->firstOrFail();
        $this->assertSame(DoctorApprovedNotification::class, $notification->type);
        $this->assertSame('doctor_verification_approved', $notification->data['type']);
    }

    public function test_admin_can_reject_pending_doctor_and_doctor_sees_reason(): void
    {
        $doctor = $this->createDoctor();
        $admin = $this->actingAsAdmin();
        $reason = 'The medical license document is unclear.';

        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/reject", [
            'rejection_reason' => $reason,
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', $reason);

        $profile = $doctor->doctorProfile->fresh();
        $this->assertSame(DoctorVerificationStatus::Rejected, $profile->verification_status);
        $this->assertSame($admin->id, $profile->reviewed_by);
        $this->assertNull($profile->approved_at);

        $notification = $doctor->notifications()->firstOrFail();
        $this->assertSame(DoctorRejectedNotification::class, $notification->type);
        $this->assertSame($reason, $notification->data['rejection_reason']);

        Sanctum::actingAs($doctor);
        $this->getJson('/api/v1/doctor/auth/verification-status')
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', $reason);
    }

    public function test_rejection_reason_is_required_and_validated(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAsAdmin();

        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');

        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/reject", [
            'rejection_reason' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
    }

    public function test_already_reviewed_application_returns_conflict(): void
    {
        $doctor = $this->createDoctor([], [
            'verification_status' => DoctorVerificationStatus::Approved,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
        $this->actingAsAdmin();

        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/approve")
            ->assertConflict();
        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/reject", [
            'rejection_reason' => 'A sufficiently long rejection reason.',
        ])->assertConflict();
        $this->assertDatabaseCount('doctor_verification_audits', 0);
    }

    public function test_approved_doctor_passes_approval_middleware(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAsAdmin();
        $this->patchJson("/api/v1/admin/doctor-applications/{$doctor->id}/approve")->assertOk();

        $middleware = app(EnsureDoctorIsApproved::class);
        $request = request()->setUserResolver(fn () => $doctor->fresh());
        $response = $middleware->handle($request, fn () => response()->json(['allowed' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_verification_documents_require_admin_and_are_scoped_to_doctor(): void
    {
        Storage::fake('local');
        $doctor = $this->createDoctor();
        $otherDoctor = $this->createDoctor([
            'email' => 'other@example.com',
            'phone' => '+15552220002',
        ]);
        Storage::disk('local')->put('doctor-verification/license.pdf', 'private-pdf');
        $document = DoctorVerificationDocument::create([
            'doctor_profile_id' => $doctor->doctorProfile->id,
            'document_type' => 'medical_license',
            'original_name' => 'license.pdf',
            'disk' => 'local',
            'path' => 'doctor-verification/license.pdf',
            'mime_type' => 'application/pdf',
            'size' => 11,
        ]);
        $url = "/api/v1/admin/doctor-applications/{$doctor->id}/documents/{$document->id}";

        $this->get($url, ['Accept' => 'application/json'])->assertUnauthorized();

        Sanctum::actingAs($doctor);
        $this->get($url, ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAsAdmin();
        $this->get("/api/v1/admin/doctor-applications/{$otherDoctor->id}/documents/{$document->id}")
            ->assertNotFound();
        $this->get($url)->assertOk();
    }

    private function actingAsAdmin(): User
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '+15550000001',
            'password' => self::PASSWORD,
        ]);
        $admin->assignRole('platform_admin');

        return $admin;
    }

    private function createDoctor(array $userOverrides = [], array $profileOverrides = []): User
    {
        static $sequence = 1000;
        $sequence++;

        $user = User::factory()->create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Doctor',
            'email' => "doctor{$sequence}@example.com",
            'phone' => '+1555'.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT),
            'password' => self::PASSWORD,
        ], $userOverrides));
        $user->assignRole('doctor');
        DoctorProfile::create(array_merge([
            'user_id' => $user->id,
            'specialty_id' => Specialty::query()->value('id'),
            'medical_license_number' => 'LIC-'.$sequence,
            'years_of_experience' => 7,
            'bio' => 'Board-certified physician.',
            'verification_status' => DoctorVerificationStatus::Pending,
            'application_submitted_at' => now(),
        ], $profileOverrides));

        return $user;
    }
}
