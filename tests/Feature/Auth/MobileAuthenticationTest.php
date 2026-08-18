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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Valid#Pass1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, SpecialtySeeder::class]);
    }

    public function test_patient_and_doctor_logins_create_persistent_device_tokens(): void
    {
        $patient = $this->createPatient();
        $doctor = $this->createDoctor();

        $patientLogin = $this->postJson('/api/v1/patient/auth/login', [
            'email' => $patient->email,
            'password' => self::PASSWORD,
            'device_name' => 'Mona iPhone',
        ])->assertOk();
        $doctorLogin = $this->postJson('/api/v1/doctor/auth/login', [
            'email' => $doctor->email,
            'password' => self::PASSWORD,
            'device_name' => 'Omar Android',
        ])->assertOk();

        $this->assertNull(config('sanctum.expiration'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $patient->id,
            'name' => 'Mona iPhone',
            'expires_at' => null,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $doctor->id,
            'name' => 'Omar Android',
            'expires_at' => null,
        ]);

        $patientToken = $patientLogin->json('data.token');
        $doctorToken = $doctorLogin->json('data.token');

        $this->withToken($patientToken)->getJson('/api/v1/patient/auth/me')->assertOk();
        $this->withToken($patientToken)->getJson('/api/v1/patient/auth/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($doctorToken)->getJson('/api/v1/doctor/auth/me')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_every_login_creates_a_separate_device_session(): void
    {
        $patient = $this->createPatient();

        foreach (['Personal iPhone', 'Work iPad'] as $deviceName) {
            $this->postJson('/api/v1/patient/auth/login', [
                'email' => $patient->email,
                'password' => self::PASSWORD,
                'device_name' => $deviceName,
            ])->assertOk();
        }

        $this->assertCount(2, $patient->fresh()->tokens);
        $this->assertEqualsCanonicalizing(
            ['Personal iPhone', 'Work iPad'],
            $patient->fresh()->tokens->pluck('name')->all(),
        );
    }

    public function test_user_can_list_only_their_own_device_sessions_without_plain_text_tokens(): void
    {
        $patient = $this->createPatient();
        $otherPatient = $this->createPatient([
            'email' => 'other-patient@example.com',
            'phone' => '+15550100002',
        ]);
        $currentToken = $patient->createToken('Current Phone');
        $patient->createToken('Tablet');
        $otherPatient->createToken('Other User Phone');

        $response = $this->withToken($currentToken->plainTextToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $sessions = collect($response->json('data'));
        $this->assertEqualsCanonicalizing(['Current Phone', 'Tablet'], $sessions->pluck('device_name')->all());
        $this->assertSame(1, $sessions->where('current', true)->count());
        $this->assertStringNotContainsString('plainTextToken', $response->getContent());
        $this->assertStringNotContainsString($currentToken->plainTextToken, $response->getContent());
    }

    public function test_user_can_revoke_a_specific_own_device_but_not_another_users_device(): void
    {
        $patient = $this->createPatient();
        $otherPatient = $this->createPatient([
            'email' => 'other-patient@example.com',
            'phone' => '+15550100002',
        ]);
        $currentToken = $patient->createToken('Current Phone');
        $tabletToken = $patient->createToken('Tablet')->accessToken;
        $otherToken = $otherPatient->createToken('Other User Phone')->accessToken;

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson("/api/v1/auth/sessions/{$otherToken->id}")
            ->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson("/api/v1/auth/sessions/{$tabletToken->id}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tabletToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
    }

    public function test_logout_revokes_only_the_current_device_token(): void
    {
        $patient = $this->createPatient();
        $phone = $patient->createToken('Phone');
        $tablet = $patient->createToken('Tablet')->accessToken;

        $this->withToken($phone->plainTextToken)
            ->postJson('/api/v1/patient/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $phone->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tablet->id]);
    }

    public function test_logout_all_revokes_every_device_token(): void
    {
        $doctor = $this->createDoctor();
        $phone = $doctor->createToken('Phone');
        $doctor->createToken('Tablet');

        $this->withToken($phone->plainTextToken)
            ->postJson('/api/v1/doctor/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => $doctor->getMorphClass(),
            'tokenable_id' => $doctor->id,
        ]);
    }

    public function test_inactive_middleware_blocks_and_revokes_an_existing_token(): void
    {
        $patient = $this->createPatient();
        $token = $patient->createToken('Phone');

        DB::table('users')->where('id', $patient->id)->update(['is_active' => false]);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/patient/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_deactivation_and_password_change_revoke_all_tokens(): void
    {
        $patient = $this->createPatient();
        $patient->createToken('Phone');
        $patient->createToken('Tablet');

        $patient->update(['is_active' => false]);
        $this->assertCount(0, $patient->fresh()->tokens);

        $patient->update(['is_active' => true]);
        $patient->createToken('New Phone');
        $patient->createToken('New Tablet');
        $patient->update(['password' => 'Changed#Pass2']);

        $this->assertCount(0, $patient->fresh()->tokens);
    }

    private function createPatient(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => 'patient@example.com',
            'phone' => '+15550100001',
            'password' => self::PASSWORD,
        ], $overrides));
        $user->assignRole('patient');
        PatientProfile::create([
            'user_id' => $user->id,
            'date_of_birth' => '1995-05-12',
            'gender' => 'female',
        ]);

        return $user;
    }

    private function createDoctor(): User
    {
        $user = User::factory()->create([
            'email' => 'doctor@example.com',
            'phone' => '+15550200001',
            'password' => self::PASSWORD,
        ]);
        $user->assignRole('doctor');
        DoctorProfile::create([
            'user_id' => $user->id,
            'specialty_id' => Specialty::query()->value('id'),
            'medical_license_number' => 'MOBILE-LICENSE-1',
            'years_of_experience' => 7,
            'verification_status' => DoctorVerificationStatus::Pending,
            'application_submitted_at' => now(),
        ]);

        return $user;
    }
}
