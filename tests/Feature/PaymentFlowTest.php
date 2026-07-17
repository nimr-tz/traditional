<?php

namespace Tests\Feature;

use App\Mail\ControlNumberIssued;
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
        Mail::fake();

        $user = User::factory()->create(['is_east_africa' => true, 'fee_amount' => 150000]);

        $this->actingAs($user)->post('/payment/control-number')->assertRedirect();

        $user->refresh();
        $this->assertSame('submitted', $user->payment_status);
        // Sync queue connection in testing runs the sandbox assignment job inline.
        $this->assertNotNull($user->control_number);
        Mail::assertQueued(ControlNumberIssued::class, fn ($mail) => $mail->hasTo($user->email));
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

    public function test_requesting_a_control_number_twice_does_not_create_a_duplicate_billing_request(): void
    {
        $user = User::factory()->create(['is_east_africa' => true, 'fee_amount' => 150000]);

        $this->actingAs($user)->post('/payment/control-number');

        $user->refresh();
        $firstBillingRequestId = $user->billing_request_id;
        $firstControlNumber = $user->control_number;
        $this->assertNotNull($firstBillingRequestId);
        $this->assertNotNull($firstControlNumber);

        $this->actingAs($user)->post('/payment/control-number')->assertRedirect();

        $user->refresh();
        $this->assertSame($firstBillingRequestId, $user->billing_request_id);
        $this->assertSame($firstControlNumber, $user->control_number);
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
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL1',
            'fee_amount' => 100,
            'currency' => 'TZS',
        ]);

        $response = $this->post('/api/billing/control-number-callback', [
            'req_id' => 'REQ1',
            'bill_id' => 'BILL1',
            'cntrl_num' => 'CNTRL1',
            'bill_amt' => '100.00',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame('CNTRL1', $user->control_number);
        Mail::assertQueued(ControlNumberIssued::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_payment_callback_matches_api_md_shape(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL1',
            'control_number' => 'CNTRL1',
            'payment_status' => 'submitted',
            'fee_amount' => 100,
            'currency' => 'TZS',
        ]);

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

    public function test_payment_callback_rejects_an_amount_or_currency_mismatch(): void
    {
        $user = User::factory()->create([
            'billing_request_id' => 'BILL2',
            'control_number' => 'CNTRL2',
            'payment_status' => 'submitted',
            'fee_amount' => 150000,
            'currency' => 'TZS',
        ]);

        $this->postJson('/api/billing/payment-callback', [
            'bill_id' => 'BILL2',
            'trx_id' => 'TRX-MISMATCH',
            'bill_amt' => '150000.00',
            'paid_amt' => '100.00',
            'paid_ccy' => 'USD',
        ])->assertUnprocessable();

        $this->assertSame('submitted', $user->fresh()->payment_status);
        $this->assertNull($user->fresh()->payment_transaction_id);
    }

    public function test_duplicate_payment_callback_is_idempotent(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL3',
            'control_number' => 'CNTRL3',
            'payment_status' => 'submitted',
            'fee_amount' => 100,
            'currency' => 'TZS',
        ]);

        $payload = [
            'bill_id' => 'BILL3',
            'trx_id' => 'TRX-DUPLICATE',
            'bill_amt' => '100.00',
            'paid_amt' => '100.00',
            'paid_ccy' => 'TZS',
        ];

        $this->postJson('/api/billing/payment-callback', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => false]);
        $this->postJson('/api/billing/payment-callback', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        Mail::assertQueued(PaymentConfirmed::class, 1);
    }

    public function test_billing_callback_token_is_enforced_when_configured(): void
    {
        config(['billing.callback_token' => 'callback-secret']);

        $this->postJson('/api/billing/control-number-callback', [
            'bill_id' => 'BILL4',
            'cntrl_num' => 'CNTRL4',
            'bill_amt' => '100.00',
        ])->assertForbidden();
    }

    public function test_admin_cannot_manually_verify_a_pending_payment(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['payment_status' => 'submitted', 'control_number' => '123456789012']);

        $this->actingAs($admin)->post("/admin/registrations/{$user->id}/verify")->assertNotFound();

        $user->refresh();
        $this->assertSame('submitted', $user->payment_status);
        Mail::assertNothingQueued();
    }
}
