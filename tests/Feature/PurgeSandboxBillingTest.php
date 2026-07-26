<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeSandboxBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_run_while_billing_is_still_in_sandbox_mode(): void
    {
        config(['billing.sandbox' => true]);

        $user = User::factory()->create([
            'billing_request_id' => 'SANDBOX-ABC123',
            'control_number' => '916992275599',
            'payment_status' => 'submitted',
        ]);

        $this->artisan('billing:purge-sandbox')->assertFailed();

        $this->assertSame('916992275599', $user->fresh()->control_number);
    }

    public function test_it_clears_a_fake_control_number_for_an_unpaid_registrant(): void
    {
        config(['billing.sandbox' => false]);

        $user = User::factory()->create([
            'billing_request_id' => 'SANDBOX-ABC123',
            'billing_requested_at' => now(),
            'control_number' => '916992275599',
            'payment_status' => 'submitted',
        ]);

        $this->artisan('billing:purge-sandbox')->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->control_number);
        $this->assertNull($user->billing_request_id);
        $this->assertNull($user->billing_requested_at);
        $this->assertSame('pending', $user->payment_status);
    }

    public function test_it_revokes_a_simulated_payment_and_its_badge_code(): void
    {
        config(['billing.sandbox' => false]);

        $user = User::factory()->create([
            'billing_request_id' => 'SANDBOX-ABC123',
            'payment_status' => 'verified',
            'paid_at' => now(),
            'registration_code' => 'TMSC-FAKE12345',
            'payment_verified_by' => null,
        ]);

        $this->artisan('billing:purge-sandbox')->assertSuccessful();

        $user->refresh();
        $this->assertSame('pending', $user->payment_status);
        $this->assertNull($user->paid_at);
        $this->assertNull($user->registration_code);
        $this->assertNull($user->billing_request_id);
    }

    public function test_it_preserves_a_payment_a_human_at_finance_decided(): void
    {
        config(['billing.sandbox' => false]);

        $finance = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $user = User::factory()->create([
            'billing_request_id' => 'SANDBOX-ABC123',
            'payment_status' => 'waived',
            'paid_at' => now(),
            'registration_code' => 'TMSC-REAL12345',
            'payment_verified_by' => $finance->id,
        ]);

        $this->artisan('billing:purge-sandbox')->assertSuccessful();

        $user->refresh();
        $this->assertSame('waived', $user->payment_status);
        $this->assertSame('TMSC-REAL12345', $user->registration_code);
        $this->assertNotNull($user->paid_at);
        $this->assertNull($user->billing_request_id);
    }

    public function test_it_leaves_real_gateway_billing_records_untouched(): void
    {
        config(['billing.sandbox' => false]);

        $user = User::factory()->create([
            'billing_request_id' => '7c9f1b2a-real-bill-id',
            'control_number' => '991234567890',
            'payment_status' => 'submitted',
        ]);

        $this->artisan('billing:purge-sandbox')->assertSuccessful();

        $user->refresh();
        $this->assertSame('991234567890', $user->control_number);
        $this->assertSame('7c9f1b2a-real-bill-id', $user->billing_request_id);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        config(['billing.sandbox' => false]);

        $user = User::factory()->create([
            'billing_request_id' => 'SANDBOX-ABC123',
            'control_number' => '916992275599',
            'payment_status' => 'submitted',
        ]);

        $this->artisan('billing:purge-sandbox --dry-run')->assertSuccessful();

        $this->assertSame('916992275599', $user->fresh()->control_number);
    }
}
