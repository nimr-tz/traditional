<?php

namespace Tests\Feature;

use App\Jobs\AssignSandboxControlNumber;
use App\Mail\ControlNumberIssued;
use App\Mail\PaymentConfirmed;
use App\Models\ConferenceSetting;
use App\Models\User;
use App\Services\Billing\GepgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
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

    public function test_sandbox_control_number_can_be_replaced_after_live_billing_is_enabled(): void
    {
        Http::fake([
            'https://billing.example/api/bill-submission/' => Http::response(['bill_id' => 'LIVE-BILL-123'], 201),
        ]);

        config([
            'billing.sandbox' => false,
            'billing.system_url' => 'https://billing.example',
            'billing.api_key' => 'secret',
            'billing.mapping.participant_east_africa.rev_src_id' => 46,
        ]);

        $user = User::factory()->create([
            'fee_category' => 'participant_east_africa',
            'fee_amount' => 150000,
            'currency' => 'TZS',
            'payment_status' => 'submitted',
            'billing_request_id' => 'SANDBOX-OLD-REQUEST',
            'control_number' => '999999999999',
        ]);

        $this->actingAs($user)
            ->get('/payment')
            ->assertInertia(fn (Assert $page) => $page
                ->where('canReplaceSandboxControlNumber', true));

        $this->actingAs($user)
            ->post('/payment/control-number')
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('LIVE-BILL-123', $user->billing_request_id);
        $this->assertNull($user->control_number);
        $this->assertSame('submitted', $user->payment_status);

        Http::assertSent(fn ($request) => $request->url() === 'https://billing.example/api/bill-submission/'
            && $request['revenue_source'] === 46
            && $request['amount'] === '150000.00');
    }

    public function test_user_who_has_not_requested_a_control_number_sees_the_normal_payment_flow(): void
    {
        config(['billing.sandbox' => false]);

        $user = User::factory()->create([
            'payment_status' => 'pending',
            'billing_request_id' => null,
            'control_number' => null,
        ]);

        $this->actingAs($user)
            ->get('/payment')
            ->assertInertia(fn (Assert $page) => $page
                ->where('canReplaceSandboxControlNumber', false)
                ->where('user.payment_status', 'pending')
                ->where('user.control_number', null));
    }

    public function test_existing_live_billing_request_cannot_be_replaced(): void
    {
        Http::fake();
        config(['billing.sandbox' => false]);

        $user = User::factory()->create([
            'payment_status' => 'submitted',
            'billing_request_id' => 'LIVE-BILL-123',
            'control_number' => '123456789012',
        ]);

        $this->actingAs($user)
            ->post('/payment/control-number')
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertSame('LIVE-BILL-123', $user->fresh()->billing_request_id);
        $this->assertSame('123456789012', $user->fresh()->control_number);
        Http::assertNothingSent();
    }

    public function test_delayed_sandbox_job_does_not_overwrite_a_live_billing_request(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'payment_status' => 'submitted',
            'billing_request_id' => 'LIVE-BILL-123',
            'control_number' => null,
        ]);

        (new AssignSandboxControlNumber($user->id))->handle(app(GepgService::class));

        $this->assertNull($user->fresh()->control_number);
        Mail::assertNothingQueued();
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

    /**
     * The payload the NIMR Billing System actually sends, copied from
     * billing/tasks.py: `cntr_num` rather than api.md's `cntrl_num`, as an
     * integer, with no amount field at all. Requiring `cntrl_num` and
     * `bill_amt` made every real callback 422, so control numbers were never
     * recorded and registrants stayed stuck in "submitted".
     */
    public function test_control_number_callback_accepts_the_billing_systems_real_payload(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL9',
            'fee_amount' => 150,
            'currency' => 'USD',
        ]);

        $this->postJson('/api/billing/control-number-callback', [
            'req_id' => 'REQ9',
            'bill_id' => 'BILL9',
            'cntr_num' => 916992275599,
            'bill_print_url' => 'http://10.0.10.53/billing/bills/BILL9/transfer/',
        ])->assertOk();

        $this->assertSame('916992275599', $user->fresh()->control_number);
        Mail::assertQueued(ControlNumberIssued::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_assessment_alias_accepts_the_billing_systems_real_payload(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL13',
            'fee_amount' => 150,
            'currency' => 'USD',
        ]);

        $this->postJson('/api/billing/assessment', [
            'req_id' => 'REQ13',
            'bill_id' => 'BILL13',
            'cntr_num' => 916992275513,
            'bill_print_url' => 'http://10.0.10.53/billing/bills/BILL13/transfer/',
        ])->assertOk();

        $this->assertSame('916992275513', $user->fresh()->control_number);
        Mail::assertQueued(ControlNumberIssued::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_control_number_callback_is_rejected_from_an_ip_outside_the_allowlist(): void
    {
        config(['billing.callback_allowed_ips' => '10.0.10.53']);

        $user = User::factory()->create(['billing_request_id' => 'BILL10']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/billing/control-number-callback', [
                'bill_id' => 'BILL10',
                'cntr_num' => 916992275500,
            ])->assertForbidden();

        $this->assertNull($user->fresh()->control_number);
    }

    public function test_control_number_callback_is_accepted_from_an_allowlisted_ip(): void
    {
        Mail::fake();
        config(['billing.callback_allowed_ips' => '10.0.10.53, 10.0.10.54']);

        $user = User::factory()->create(['billing_request_id' => 'BILL11']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.10.54'])
            ->postJson('/api/billing/control-number-callback', [
                'bill_id' => 'BILL11',
                'cntr_num' => 916992275501,
            ])->assertOk();

        $this->assertSame('916992275501', $user->fresh()->control_number);
    }

    public function test_payment_callback_accepts_the_billing_systems_real_payload(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL12',
            'control_number' => '916992275502',
            'payment_status' => 'submitted',
            'fee_amount' => 150,
            'currency' => 'USD',
        ]);

        // Mirrors build_payment_notification_payload() in billing/payment_notifications.py.
        $this->postJson('/api/billing/payment-callback', [
            'bill_id' => 'BILL12',
            'cntr_num' => 916992275502,
            'psp_code' => 'CRDB',
            'psp_name' => 'CRDB Bank',
            'trx_id' => 'TRX-REAL-1',
            'payref_id' => 'PAYREF-1',
            'bill_amt' => '150.00',
            'paid_amt' => '150.00',
            'paid_ccy' => 'USD',
            'coll_acc_num' => '0150XXXXXXXXX',
            'trx_date' => '2026-07-26T10:00:00',
            'pay_channel' => 'BANK',
            'pyr_cell_num' => '255700000000',
            'bill_receipt_url' => 'http://10.0.10.53/billing/bills/BILL12/receipt/',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('verified', $user->payment_status);
        $this->assertSame('TRX-REAL-1', $user->payment_transaction_id);
        $this->assertNotNull($user->registration_code);
    }

    public function test_payment_alias_accepts_the_billing_systems_real_payload(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'billing_request_id' => 'BILL14',
            'control_number' => '916992275514',
            'payment_status' => 'submitted',
            'fee_amount' => 150,
            'currency' => 'USD',
        ]);

        $this->postJson('/api/billing/payment', [
            'bill_id' => 'BILL14',
            'cntr_num' => 916992275514,
            'psp_code' => 'CRDB',
            'psp_name' => 'CRDB Bank',
            'trx_id' => 'TRX-REAL-2',
            'payref_id' => 'PAYREF-2',
            'bill_amt' => '150.00',
            'paid_amt' => '150.00',
            'paid_ccy' => 'USD',
            'coll_acc_num' => '0150XXXXXXXXX',
            'trx_date' => '2026-07-26T10:00:00',
            'pay_channel' => 'BANK',
            'pyr_cell_num' => '255700000000',
            'bill_receipt_url' => 'http://10.0.10.53/billing/bills/BILL14/receipt/',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('verified', $user->payment_status);
        $this->assertSame('TRX-REAL-2', $user->payment_transaction_id);
        $this->assertNotNull($user->registration_code);
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

    public function test_a_production_environment_refuses_to_issue_a_sandbox_control_number(): void
    {
        $this->app['env'] = 'production';
        config(['billing.sandbox' => true]);

        $user = User::factory()->create(['is_east_africa' => true, 'fee_amount' => 150000]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payments are not available yet.');

        app(GepgService::class)->requestControlNumber($user);
    }

    /**
     * A walk-in has one `name` and may have given the desk neither an email nor
     * a phone (WalkInRegistrar::rules). The bill still has to carry a valid
     * customer, so the conference's own contacts stand in — previously this
     * sent `email: null` and a padded `cell_num` of 255000000000, and the
     * billing system rejected the submission.
     */
    public function test_a_desk_walk_in_without_contact_details_still_submits_a_valid_customer(): void
    {
        Http::fake([
            'https://billing.example/api/bill-submission/' => Http::response(['bill_id' => 'LIVE-BILL-777'], 201),
        ]);

        config([
            'billing.sandbox' => false,
            'billing.system_url' => 'https://billing.example',
            'billing.api_key' => 'secret',
        ]);

        ConferenceSetting::set('contact_email', 'tmsc@nimr.or.tz');
        ConferenceSetting::set('contact_phone', '0754 111 222');

        $user = User::factory()->create([
            'name' => 'Jane Anna Doe',
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'email' => null,
            'phone' => null,
            'fee_category' => 'participant_east_africa',
            'fee_amount' => 150000,
            'currency' => 'TZS',
        ]);

        app(GepgService::class)->requestControlNumber($user);

        Http::assertSent(fn ($request) => $request['customer'] === [
            'first_name' => 'Jane',
            'middle_name' => 'Anna',
            'last_name' => 'Doe',
            'cell_num' => '255754111222',
            'email' => 'tmsc@nimr.or.tz',
        ]);

        $this->assertSame('LIVE-BILL-777', $user->fresh()->billing_request_id);
    }

    public function test_a_self_registered_customer_payload_is_unchanged(): void
    {
        Http::fake([
            'https://billing.example/api/bill-submission/' => Http::response(['bill_id' => 'LIVE-BILL-778'], 201),
        ]);

        config([
            'billing.sandbox' => false,
            'billing.system_url' => 'https://billing.example',
            'billing.api_key' => 'secret',
        ]);

        $user = User::factory()->create([
            'first_name' => 'John',
            'middle_name' => null,
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+255 700 000 000',
            'fee_category' => 'participant_east_africa',
            'fee_amount' => 150000,
            'currency' => 'TZS',
        ]);

        app(GepgService::class)->requestControlNumber($user);

        Http::assertSent(fn ($request) => $request['customer'] === [
            'first_name' => 'John',
            'middle_name' => 'no middle name',
            'last_name' => 'Doe',
            'cell_num' => '255700000000',
            'email' => 'john.doe@example.com',
        ]);
    }
}
