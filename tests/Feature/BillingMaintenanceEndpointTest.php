<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingMaintenanceEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function sandboxRegistrant(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'billing_request_id' => 'SANDBOX-ABC123',
            'billing_requested_at' => now(),
            'control_number' => '916992275599',
            'payment_status' => 'submitted',
        ], $overrides));
    }

    public function test_guests_cannot_reach_the_purge_endpoint(): void
    {
        $this->postJson('/admin/billing/purge-sandbox')->assertUnauthorized();
    }

    public function test_registrants_and_plain_admins_cannot_purge(): void
    {
        config(['billing.sandbox' => false]);

        foreach ([User::ROLE_USER, User::ROLE_FINANCE, User::ROLE_ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)
                ->postJson('/admin/billing/purge-sandbox')
                ->assertForbidden();
        }
    }

    public function test_it_refuses_while_billing_is_still_in_sandbox_mode(): void
    {
        config(['billing.sandbox' => true]);

        $user = $this->sandboxRegistrant();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/billing/purge-sandbox', ['dry_run' => false])
            ->assertStatus(409)
            ->assertJson(['applied' => false, 'sandbox_mode' => true]);

        $this->assertSame('916992275599', $user->fresh()->control_number);
    }

    public function test_it_defaults_to_a_dry_run_and_writes_nothing(): void
    {
        config(['billing.sandbox' => false]);

        $user = $this->sandboxRegistrant();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/billing/purge-sandbox')
            ->assertOk()
            ->assertJson(['applied' => false, 'dry_run' => true, 'count' => 1])
            ->assertJsonPath('records.0.id', $user->id)
            ->assertJsonPath('records.0.action', 'reset_to_pending');

        $this->assertSame('916992275599', $user->fresh()->control_number);
    }

    public function test_it_applies_when_dry_run_is_explicitly_false(): void
    {
        config(['billing.sandbox' => false]);

        $user = $this->sandboxRegistrant();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/billing/purge-sandbox', ['dry_run' => false])
            ->assertOk()
            ->assertJson(['applied' => true, 'count' => 1]);

        $user->refresh();
        $this->assertNull($user->control_number);
        $this->assertNull($user->billing_request_id);
        $this->assertSame('pending', $user->payment_status);
    }

    public function test_it_revokes_a_simulated_payment_and_flags_issued_credentials(): void
    {
        config(['billing.sandbox' => false]);

        $user = $this->sandboxRegistrant([
            'control_number' => null,
            'payment_status' => 'verified',
            'paid_at' => now(),
            'registration_code' => 'TMSC-FAKE12345',
            'payment_verified_by' => null,
        ]);
        $user->attendance()->create(['checked_in_at' => now(), 'checked_in_by' => $user->id]);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/billing/purge-sandbox', ['dry_run' => false])
            ->assertOk()
            ->assertJsonPath('records.0.action', 'revoke_simulated_payment')
            ->assertJsonPath('records.0.needs_manual_review', true);

        $user->refresh();
        $this->assertSame('pending', $user->payment_status);
        $this->assertNull($user->registration_code);
        $this->assertNull($user->paid_at);
    }

    public function test_it_preserves_a_payment_a_human_at_finance_decided(): void
    {
        config(['billing.sandbox' => false]);

        $finance = User::factory()->create(['role' => User::ROLE_FINANCE]);
        $user = $this->sandboxRegistrant([
            'control_number' => null,
            'payment_status' => 'waived',
            'paid_at' => now(),
            'registration_code' => 'TMSC-REAL12345',
            'payment_verified_by' => $finance->id,
        ]);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/billing/purge-sandbox', ['dry_run' => false])
            ->assertOk()
            ->assertJsonPath('records.0.action', 'strip_ids');

        $user->refresh();
        $this->assertSame('waived', $user->payment_status);
        $this->assertSame('TMSC-REAL12345', $user->registration_code);
        $this->assertNull($user->billing_request_id);
    }

    public function test_it_leaves_real_gateway_records_untouched(): void
    {
        config(['billing.sandbox' => false]);

        $user = $this->sandboxRegistrant([
            'billing_request_id' => '7c9f1b2a-real-bill-id',
            'control_number' => '991234567890',
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/billing/purge-sandbox', ['dry_run' => false])
            ->assertOk()
            ->assertJson(['count' => 0, 'records' => []]);

        $this->assertSame('991234567890', $user->fresh()->control_number);
    }
}
