<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_page_only_exposes_customer_facing_billing_information(): void
    {
        $user = User::factory()->create([
            'billing_request_id' => 'INTERNAL-BILL-ID',
            'payment_notes' => 'Internal billing note',
        ]);

        $this->actingAs($user)
            ->get('/payment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('payment')
                ->missing('sandbox')
                ->missing('user.billing_request_id')
                ->missing('user.payment_notes'));
    }

    public function test_east_africa_registrant_can_request_and_receive_a_sandbox_control_number(): void
    {
        $user = User::factory()->create(['is_east_africa' => true, 'fee_amount' => 150000]);

        $this->actingAs($user)->post('/payment/control-number')->assertRedirect();

        $user->refresh();
        $this->assertSame('submitted', $user->payment_status);
        // Sync queue connection in testing runs the sandbox assignment job inline.
        $this->assertNotNull($user->control_number);
    }

    public function test_sandbox_gepg_callback_marks_payment_verified_and_generates_a_registration_code(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_east_africa' => true, 'control_number' => '123456789012']);

        $this->post("/api/billing/sandbox/simulate/{$user->id}")->assertRedirect();

        $user->refresh();
        $this->assertSame('verified', $user->payment_status);
        $this->assertNotNull($user->registration_code);
        $this->assertNotNull($user->paid_at);
        Mail::assertQueued(PaymentConfirmed::class);
    }

    public function test_non_east_africa_registrant_can_also_request_a_control_number(): void
    {
        $user = User::factory()->create(['is_east_africa' => false, 'fee_amount' => 150]);

        $this->actingAs($user)->post('/payment/control-number')->assertRedirect();

        $user->refresh();
        $this->assertSame('submitted', $user->payment_status);
        $this->assertNotNull($user->control_number);
    }

    public function test_control_number_callback_matches_api_md_shape(): void
    {
        $user = User::factory()->create(['billing_request_id' => 'BILL1']);

        $response = $this->post('/api/billing/control-number-callback', [
            'req_id' => 'REQ1',
            'bill_id' => 'BILL1',
            'cntrl_num' => 'CNTRL1',
            'bill_amt' => '100.00',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame('CNTRL1', $user->control_number);
    }

    public function test_payment_callback_matches_api_md_shape(): void
    {
        Mail::fake();

        $user = User::factory()->create(['billing_request_id' => 'BILL1', 'control_number' => 'CNTRL1', 'payment_status' => 'submitted']);

        $response = $this->post('/api/billing/payment-callback', [
            'bill_id' => 'BILL1',
            'psp_code' => 'PSP01',
            'psp_name' => 'Provider',
            'trx_id' => 'TRX123',
            'payref_id' => 'PAYREF1',
            'bill_amt' => '100.00',
            'paid_amt' => '100.00',
            'paid_ccy' => 'TZS',
            'coll_acc_num' => '000000000000',
            'trx_date' => '2026-01-01T10:00:00Z',
            'pay_channel' => 'BANK',
            'pay_cell_num' => '255700000000',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame('verified', $user->payment_status);
        $this->assertNotNull($user->registration_code);
        Mail::assertQueued(PaymentConfirmed::class);
    }

    public function test_admin_cannot_manually_verify_a_pending_payment(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['payment_status' => 'submitted', 'control_number' => '123456789012']);

        $this->actingAs($admin)->post("/admin/registrations/{$user->id}/verify")->assertNotFound();

        $user->refresh();
        $this->assertSame('submitted', $user->payment_status);
        Mail::assertNothingQueued();
    }
}
