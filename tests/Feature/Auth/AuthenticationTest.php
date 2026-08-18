<?php

namespace Tests\Feature\Auth;

use App\Enums\DoctorVerificationStatus;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Valid#Pass1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, SpecialtySeeder::class]);
    }

    public function test_successful_patient_registration(): void
    {
        $response = $this->postJson('/api/v1/patient/auth/register', $this->patientRegistrationData());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'patient')
            ->assertJsonPath('data.verification_status', null)
            ->assertJsonPath('data.profile.gender', 'female')
            ->assertJsonStructure(['data' => ['token', 'token_type']])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'patient@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('patient'));
        $this->assertDatabaseHas('patient_profiles', ['user_id' => $user->id]);
        $this->assertSame('+15551234567', $user->phone);
    }

    public function test_successful_patient_login_updates_last_login_time(): void
    {
        $patient = $this->createPatient();

        $response = $this->postJson('/api/v1/patient/auth/login', [
            'email' => strtoupper($patient->email),
            'password' => self::PASSWORD,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.role', 'patient')
            ->assertJsonStructure(['data' => ['token']]);
        $this->assertNotNull($patient->fresh()->last_login_at);
    }

    public function test_successful_doctor_registration_defaults_to_pending(): void
    {
        $response = $this->postJson('/api/v1/doctor/auth/register', $this->doctorRegistrationData());

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', 'doctor')
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.profile.verification_status', 'pending')
            ->assertJsonStructure(['data' => ['token', 'token_type']]);

        $user = User::where('email', 'doctor@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('doctor'));
        $this->assertDatabaseHas('doctor_profiles', [
            'user_id' => $user->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_pending_doctor_can_login_and_view_verification_status(): void
    {
        $doctor = $this->createDoctor();

        $login = $this->postJson('/api/v1/doctor/auth/login', [
            'email' => $doctor->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/doctor/auth/verification-status')
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_patient_cannot_use_doctor_login_endpoint(): void
    {
        $patient = $this->createPatient();

        $this->postJson('/api/v1/doctor/auth/login', [
            'email' => $patient->email,
            'password' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_doctor_cannot_use_patient_login_endpoint(): void
    {
        $doctor = $this->createDoctor();

        $this->postJson('/api/v1/patient/auth/login', [
            'email' => $doctor->email,
            'password' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->createPatient(['email' => 'patient@example.com']);

        $this->postJson('/api/v1/patient/auth/register', $this->patientRegistrationData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_duplicate_phone_is_rejected_after_normalization(): void
    {
        $this->createPatient(['phone' => '+15551234567']);
        $data = $this->patientRegistrationData(['email' => 'different@example.com', 'phone' => '+1 (555) 123-4567']);

        $this->postJson('/api/v1/patient/auth/register', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_duplicate_medical_license_is_rejected(): void
    {
        $this->createDoctor(['medical_license_number' => 'LIC-1000']);
        $data = $this->doctorRegistrationData([
            'email' => 'another-doctor@example.com',
            'phone' => '+15550002222',
        ]);

        $this->postJson('/api/v1/doctor/auth/register', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('medical_license_number');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $patient = $this->createPatient();

        $this->postJson('/api/v1/patient/auth/login', [
            'email' => $patient->email,
            'password' => 'Wrong#Pass1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_login_is_rejected(): void
    {
        $patient = $this->createPatient(['is_active' => false]);

        $this->postJson('/api/v1/patient/auth/login', [
            'email' => $patient->email,
            'password' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_soft_deleted_user_login_is_rejected(): void
    {
        $patient = $this->createPatient();
        $patient->delete();

        $this->postJson('/api/v1/patient/auth/login', [
            'email' => $patient->email,
            'password' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_the_current_token_only(): void
    {
        $patient = $this->createPatient();
        $currentToken = $patient->createToken('current')->plainTextToken;
        $patient->createToken('other');

        $this->withToken($currentToken)
            ->postJson('/api/v1/patient/auth/logout')
            ->assertOk();

        $this->assertCount(1, $patient->fresh()->tokens);
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $patient = $this->createPatient();
        $currentToken = $patient->createToken('current')->plainTextToken;
        $patient->createToken('other');

        $this->withToken($currentToken)
            ->postJson('/api/v1/patient/auth/logout-all')
            ->assertOk();

        $this->assertCount(0, $patient->fresh()->tokens);
    }

    public function test_protected_route_requires_a_token(): void
    {
        $this->getJson('/api/v1/patient/auth/me')->assertUnauthorized();
    }

    public function test_role_authorization_blocks_a_patient_from_doctor_routes(): void
    {
        $patient = $this->createPatient();
        Sanctum::actingAs($patient);

        $this->getJson('/api/v1/doctor/auth/me')->assertForbidden();
    }

    public function test_approved_doctor_middleware_blocks_pending_and_allows_approved_doctors(): void
    {
        Route::middleware(['auth:sanctum', 'role:doctor', 'doctor.approved'])
            ->get('/api/v1/testing/approved-doctor', fn () => response()->json(['ok' => true]));

        $doctor = $this->createDoctor();
        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/testing/approved-doctor')->assertForbidden();

        $doctor->doctorProfile->update([
            'verification_status' => DoctorVerificationStatus::Approved,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);

        $this->getJson('/api/v1/testing/approved-doctor')->assertOk();
    }

    public function test_patient_profile_update_uses_authenticated_identity_only(): void
    {
        $patient = $this->createPatient();
        $other = $this->createPatient([
            'email' => 'other@example.com',
            'phone' => '+15559999999',
        ]);
        Sanctum::actingAs($patient);

        $this->patchJson('/api/v1/patient/profile', [
            'user_id' => $other->id,
            'first_name' => 'Updated',
        ])->assertOk()->assertJsonPath('data.user.id', $patient->id);

        $this->assertSame('Updated', $patient->fresh()->first_name);
        $this->assertNotSame('Updated', $other->fresh()->first_name);
    }

    private function patientRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mona',
            'last_name' => 'Ali',
            'email' => 'PATIENT@EXAMPLE.COM',
            'phone' => '+1 (555) 123-4567',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'date_of_birth' => '1995-05-12',
            'gender' => 'female',
        ], $overrides);
    }

    private function doctorRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Omar',
            'last_name' => 'Hassan',
            'email' => 'doctor@example.com',
            'phone' => '+15550001111',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'specialty_id' => Specialty::query()->value('id'),
            'medical_license_number' => 'LIC-1000',
            'years_of_experience' => 7,
            'bio' => 'Board-certified physician.',
        ], $overrides);
    }

    private function createPatient(array $overrides = []): User
    {
        $userAttributes = array_merge([
            'password' => self::PASSWORD,
            'email' => 'patient@example.com',
            'phone' => '+15551234567',
        ], $overrides);
        $profileAttributes = array_intersect_key($userAttributes, array_flip(['date_of_birth', 'gender']));
        $userAttributes = array_diff_key($userAttributes, $profileAttributes);

        $user = User::factory()->create($userAttributes);
        $user->assignRole('patient');
        PatientProfile::create(array_merge([
            'user_id' => $user->id,
            'date_of_birth' => '1995-05-12',
            'gender' => 'female',
        ], $profileAttributes));

        return $user;
    }

    private function createDoctor(array $profileOverrides = []): User
    {
        $userOverrides = array_intersect_key($profileOverrides, array_flip([
            'first_name', 'last_name', 'email', 'phone', 'password', 'is_active',
        ]));
        $profileOverrides = array_diff_key($profileOverrides, $userOverrides);

        $user = User::factory()->create(array_merge([
            'password' => self::PASSWORD,
            'email' => 'doctor@example.com',
            'phone' => '+15550001111',
        ], $userOverrides));
        $user->assignRole('doctor');
        DoctorProfile::create(array_merge([
            'user_id' => $user->id,
            'specialty_id' => Specialty::query()->value('id'),
            'medical_license_number' => 'LIC-1000',
            'years_of_experience' => 7,
            'verification_status' => DoctorVerificationStatus::Pending,
            'application_submitted_at' => now(),
        ], $profileOverrides));

        return $user;
    }
}
