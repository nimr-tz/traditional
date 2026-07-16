<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_accounts_can_log_into_the_checkin_app(): void
    {
        $attendee = User::factory()->create(['is_admin' => false, 'password' => 'password123']);

        $this->postJson('/api/checkin/login', [
            'email' => $attendee->email,
            'password' => 'password123',
        ])->assertUnprocessable();

        $staff = User::factory()->create(['is_admin' => true, 'password' => 'password123']);

        $this->postJson('/api/checkin/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_checkin_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/checkin/login', [
                'email' => $staff->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/checkin/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertStatus(429);
    }

    public function test_scanning_a_paid_registrants_code_checks_them_in(): void
    {
        $staff = User::factory()->create(['is_admin' => true]);
        $attendee = User::factory()->create([
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-TESTCODE01',
        ]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/checkin/scan', ['code' => 'TMSC-TESTCODE01']);

        $response->assertOk()->assertJson(['already_checked_in' => false]);
        $this->assertDatabaseHas('attendances', ['user_id' => $attendee->id, 'checked_in_by' => $staff->id]);
    }

    public function test_staff_can_register_an_attendance_ready_attendee(): void
    {
        $staff = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/checkin/register', [
            'name' => 'Asha Nyerere',
            'email' => 'asha@example.com',
            'phone' => '+255 700 000 000',
            'institution' => 'Community Health Centre',
            'participant_type' => 'practitioner',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Asha Nyerere')
            ->assertJsonPath('user.email', 'asha@example.com')
            ->assertJsonStructure(['user' => ['registration_code']]);

        $this->assertDatabaseHas('users', [
            'email' => 'asha@example.com',
            'payment_status' => 'verified',
            'is_admin' => false,
        ]);
    }

    public function test_attendee_registration_rejects_duplicate_email_addresses(): void
    {
        $staff = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['email' => 'used@example.com']);

        $this->actingAs($staff, 'sanctum')->postJson('/api/checkin/register', [
            'name' => 'Second Person',
            'email' => 'used@example.com',
            'phone' => '+255 711 111 111',
            'participant_type' => 'researcher',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_scanning_an_unpaid_registrants_code_is_rejected(): void
    {
        $staff = User::factory()->create(['is_admin' => true]);
        $attendee = User::factory()->create([
            'payment_status' => 'pending',
            'registration_code' => 'TMSC-UNPAID001',
        ]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/checkin/scan', ['code' => 'TMSC-UNPAID001']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('attendances', ['user_id' => $attendee->id]);
    }
}
