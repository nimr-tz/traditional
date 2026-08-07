<?php

namespace Tests\Feature;

use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class CheckinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_accounts_can_log_into_the_checkin_app(): void
    {
        $attendee = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/checkin/login', [
            'email' => $attendee->email,
            'password' => 'password123',
        ])->assertUnprocessable();

        $staff = User::factory()->staff()->create(['password' => 'password123']);

        $this->postJson('/api/checkin/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_checkin_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'password123']);

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
        $staff = User::factory()->staff()->create();
        $attendee = User::factory()->create([
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-TESTCODE01',
        ]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/checkin/scan', ['code' => 'TMSC-TESTCODE01']);

        $response->assertOk()->assertJson(['already_checked_in' => false]);
        $this->assertDatabaseHas('attendances', ['user_id' => $attendee->id, 'checked_in_by' => $staff->id]);
    }

    private function seedFeeCategory(string $key = 'participant_east_africa'): FeeCategory
    {
        return FeeCategory::create([
            'key' => $key,
            'label' => 'East African Participants',
            'amount' => 150000,
            'currency' => 'TZS',
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function walkIn(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Nyerere',
            'email' => 'asha@example.com',
            'phone' => '+255 700 000 000',
            'institution' => 'Community Health Centre',
            'participant_type' => 'practitioner',
            'country' => 'Tanzania',
            'fee_category' => 'participant_east_africa',
        ], $overrides);
    }

    public function test_a_walk_in_is_registered_unpaid_and_owes_the_real_fee(): void
    {
        $this->seedFeeCategory();
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/checkin/register', $this->walkIn());

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Asha Nyerere')
            ->assertJsonPath('user.payment_status', 'submitted')
            ->assertJsonPath('user.is_paid', false)
            ->assertJsonPath('user.registration_code', null);

        $this->assertDatabaseHas('users', [
            'email' => 'asha@example.com',
            'fee_category' => 'participant_east_africa',
            'currency' => 'TZS',
            'registration_code' => null,
            'role' => 'user',
        ]);

        $walkIn = User::where('email', 'asha@example.com')->firstOrFail();

        $this->assertSame('150000.00', $walkIn->fee_amount);
        $this->assertFalse($walkIn->isPaid());
        $this->assertNull($walkIn->paid_at);
    }

    public function test_a_walk_in_cannot_be_registered_onto_a_cheaper_regional_tier(): void
    {
        $this->seedFeeCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/checkin/register', $this->walkIn(['country' => 'Germany']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fee_category');

        $this->assertDatabaseMissing('users', ['email' => 'asha@example.com']);
    }

    public function test_attendee_registration_rejects_duplicate_email_addresses(): void
    {
        $this->seedFeeCategory();
        $staff = User::factory()->staff()->create();
        User::factory()->create(['email' => 'used@example.com']);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/checkin/register', $this->walkIn(['email' => 'used@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_a_badge_code_cannot_be_minted_for_an_unpaid_registrant(): void
    {
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->expectException(RuntimeException::class);

        $unpaid->generateRegistrationCode();
    }

    public function test_lookup_finds_unpaid_registrants_and_reports_why_they_cannot_enter(): void
    {
        $staff = User::factory()->staff()->create();
        $unpaid = User::factory()->create([
            'name' => 'Neema Unpaid',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/checkin/lookup?q=Neema')
            ->assertOk()
            ->assertJsonPath('results.0.id', $unpaid->id)
            ->assertJsonPath('results.0.payment_status', 'pending')
            ->assertJsonPath('results.0.is_paid', false);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/checkin/users/{$unpaid->id}/check-in")
            ->assertStatus(422)
            ->assertJsonPath('user.is_paid', false);

        $this->assertDatabaseMissing('attendances', ['user_id' => $unpaid->id]);
    }

    public function test_staff_cannot_settle_a_payment_from_the_checkin_app(): void
    {
        Mail::fake();
        $staff = User::factory()->staff()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/checkin/users/{$unpaid->id}/verify-payment")
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/checkin/users/{$unpaid->id}/waive", ['notes' => 'Invited guest.'])
            ->assertForbidden();

        $unpaid->refresh();

        $this->assertSame('pending', $unpaid->payment_status);
        $this->assertNull($unpaid->registration_code);
    }

    public function test_finance_can_settle_at_the_desk_and_the_badge_follows(): void
    {
        Mail::fake();
        $finance = User::factory()->finance()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($finance, 'sanctum')
            ->postJson("/api/checkin/users/{$unpaid->id}/verify-payment", ['notes' => 'Bank slip seen at the desk.'])
            ->assertOk()
            ->assertJsonPath('user.is_paid', true);

        $unpaid->refresh();

        $this->assertSame('verified', $unpaid->payment_status);
        $this->assertSame($finance->id, $unpaid->payment_verified_by);
        $this->assertNotNull($unpaid->registration_code);
        Mail::assertQueued(PaymentConfirmed::class, fn ($mail) => $mail->hasTo($unpaid->email));

        $this->actingAs($finance, 'sanctum')
            ->postJson('/api/checkin/scan', ['code' => $unpaid->registration_code])
            ->assertOk()
            ->assertJson(['already_checked_in' => false]);
    }

    public function test_a_waiver_requires_a_reason_and_is_finance_only(): void
    {
        Mail::fake();
        $finance = User::factory()->finance()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($finance, 'sanctum')
            ->postJson("/api/checkin/users/{$unpaid->id}/waive")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notes');

        $this->actingAs($finance, 'sanctum')
            ->postJson("/api/checkin/users/{$unpaid->id}/waive", ['notes' => 'Keynote speaker, fee waived.'])
            ->assertOk();

        $unpaid->refresh();

        $this->assertSame('waived', $unpaid->payment_status);
        $this->assertSame('Keynote speaker, fee waived.', $unpaid->payment_notes);
        $this->assertNotNull($unpaid->registration_code);
        Mail::assertQueued(FeeWaived::class);
    }

    public function test_finance_can_sign_into_the_checkin_app_and_is_flagged_as_finance(): void
    {
        $finance = User::factory()->finance()->create(['password' => 'password123']);

        $this->postJson('/api/checkin/login', [
            'email' => $finance->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('user.can_manage_finance', true);

        $staff = User::factory()->staff()->create(['password' => 'password123']);

        $this->postJson('/api/checkin/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('user.can_manage_finance', false);
    }

    public function test_scanning_an_unpaid_registrants_code_is_rejected(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = User::factory()->create([
            'payment_status' => 'pending',
            'registration_code' => 'TMSC-UNPAID001',
        ]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/checkin/scan', ['code' => 'TMSC-UNPAID001']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('attendances', ['user_id' => $attendee->id]);
    }
}
