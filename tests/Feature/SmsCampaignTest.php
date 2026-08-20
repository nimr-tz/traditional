<?php

namespace Tests\Feature;

use App\Jobs\SendSmsCampaign;
use App\Models\SmsCampaign;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmsCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true]);
    }

    private function registrant(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'country' => 'Tanzania',
            'phone' => '0712345678',
            'payment_status' => 'pending',
        ], $overrides));
    }

    public function test_the_name_placeholder_is_replaced_per_recipient_at_delivery(): void
    {
        $sent = [];
        $this->mock(SmsGateway::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$sent) {
                $sent[$phone] = $message;
            });
        });

        $this->registrant(['name' => 'Asha Mushi', 'phone' => '0712345678']);
        $this->registrant(['name' => 'Juma Nyerere', 'phone' => '0713333333']);

        $campaign = SmsCampaign::create([
            'message' => 'Hi :name, the programme is out.',
            'audience' => 'all',
            'audience_label' => 'All registrants',
            'recipient_count' => 0,
            'status' => 'queued',
            'created_by_name' => 'Admin',
            'created_by_email' => 'admin@example.com',
        ]);
        $campaign->recipients()->createMany([
            ['name' => 'Asha Mushi', 'phone' => '255712345678'],
            ['name' => 'Juma Nyerere', 'phone' => '255713333333'],
        ]);

        (new SendSmsCampaign($campaign->id))->handle(app(SmsGateway::class));

        $this->assertSame('Hi Asha, the programme is out.', $sent['255712345678']);
        $this->assertSame('Hi Juma, the programme is out.', $sent['255713333333']);
    }

    /**
     * An accented name would switch the whole message to UCS-2 and drop the
     * per-part budget from 160 characters to 70 — for that recipient only,
     * which makes it the kind of cost that never shows up in testing.
     */
    public function test_a_recipient_name_cannot_push_a_message_out_of_the_plain_alphabet(): void
    {
        $sent = [];
        $this->mock(SmsGateway::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$sent) {
                $sent[$phone] = $message;
            });
        });

        $campaign = SmsCampaign::create([
            'message' => 'Hi :name, welcome.',
            'audience' => 'all',
            'audience_label' => 'All registrants',
            'recipient_count' => 0,
            'status' => 'queued',
            'created_by_name' => 'Admin',
            'created_by_email' => 'admin@example.com',
        ]);
        $campaign->recipients()->createMany([
            ['name' => 'José Álvarez', 'phone' => '255712345678'],
            ['name' => 'MWANAIDI HAMISI', 'phone' => '255713333333'],
        ]);

        (new SendSmsCampaign($campaign->id))->handle(app(SmsGateway::class));

        $this->assertSame('Hi Jose, welcome.', $sent['255712345678']);
        // Caps-lock registrations are common enough to be worth softening.
        $this->assertSame('Hi Mwanaidi, welcome.', $sent['255713333333']);
    }

    public function test_the_recipient_count_reports_the_longest_name_for_worst_case_pricing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->registrant(['name' => 'Asha Mushi', 'phone' => '0712345678']);
        $this->registrant(['name' => 'Christopher Mwakalinga', 'phone' => '0713333333']);

        $response = $this->actingAs($admin)->getJson(route('admin.sms.count', ['audience' => 'all']));

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('longest_name', 'Christopher');
    }

    public function test_a_sandboxed_test_send_does_not_claim_the_message_was_sent(): void
    {
        config(['sms.sandbox' => true]);
        $admin = User::factory()->admin()->create(['phone' => '0712345678', 'country' => 'Tanzania']);

        $response = $this->actingAs($admin)->post(route('admin.sms.test'), ['message' => 'Testing.']);

        $this->assertStringContainsString('nothing was sent', session('success'));
        $response->assertRedirect();
    }

    public function test_a_campaign_queues_one_recipient_row_per_reachable_person_in_the_segment(): void
    {
        Queue::fake();

        $this->registrant(['payment_status' => 'verified']);
        $this->registrant(['payment_status' => 'waived']);
        $this->registrant(['payment_status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), [
                'audience' => 'paid',
                'message' => 'See you in Mbeya',
            ])
            ->assertRedirect();

        $campaign = SmsCampaign::firstOrFail();

        $this->assertSame('paid', $campaign->audience);
        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame('queued', $campaign->status);
        $this->assertCount(2, $campaign->recipients);
        $this->assertSame('255712345678', $campaign->recipients->first()->phone);
        Queue::assertPushed(SendSmsCampaign::class);
    }

    /** Someone without a usable Tanzanian number is excluded before the count or the send. */
    public function test_registrants_without_a_usable_number_are_excluded(): void
    {
        Queue::fake();

        $this->registrant();
        $this->registrant(['phone' => null]);
        $this->registrant(['country' => 'Kenya', 'phone' => '0712345678']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'Hello'])
            ->assertRedirect();

        $this->assertSame(1, SmsCampaign::firstOrFail()->recipient_count);
    }

    /** Announcements must never reach the staff/reviewer/admin accounts sharing the users table. */
    public function test_campaigns_never_target_non_participant_accounts(): void
    {
        Queue::fake();

        $this->registrant();
        User::factory()->reviewer()->create(['country' => 'Tanzania', 'phone' => '0712345678']);
        User::factory()->superAdmin()->create(['country' => 'Tanzania', 'phone' => '0712345678']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'Notice']);

        $this->assertSame(1, SmsCampaign::firstOrFail()->recipient_count);
    }

    public function test_running_the_job_delivers_and_records_each_recipient(): void
    {
        $fake = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $fake);

        $this->registrant();
        $this->registrant();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'Programme published']);

        $campaign = SmsCampaign::firstOrFail();
        $this->app->call([new SendSmsCampaign($campaign->id), 'handle']);

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertNotNull($campaign->completed_at);
        $this->assertCount(2, $fake->sentTo);
    }

    public function test_a_failed_send_does_not_abort_the_rest_of_the_campaign(): void
    {
        $fake = new FakeSmsGateway(failFor: '255712345679');
        $this->app->instance(SmsGateway::class, $fake);

        $this->registrant(['phone' => '0712345678']);
        $this->registrant(['phone' => '0712345679']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'S']);

        $campaign = SmsCampaign::firstOrFail();
        $this->app->call([new SendSmsCampaign($campaign->id), 'handle']);

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertNotNull($campaign->recipients()->where('status', 'failed')->first()->error);
    }

    /**
     * The recipient list is frozen when the campaign is created. If it were
     * recomputed at delivery time, someone who paid in between would silently
     * drop out of a send the admin was told covered them.
     */
    public function test_the_recipient_list_is_frozen_at_creation_not_at_delivery(): void
    {
        $fake = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $fake);

        $willPay = $this->registrant(['payment_status' => 'pending']);
        $this->registrant(['payment_status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'unpaid', 'message' => 'Please pay']);

        $willPay->forceFill(['payment_status' => 'verified'])->save();

        $campaign = SmsCampaign::firstOrFail();
        $this->app->call([new SendSmsCampaign($campaign->id), 'handle']);

        $this->assertSame(2, $campaign->refresh()->sent_count);
        $this->assertContains('255712345678', $fake->sentTo);
    }

    public function test_a_parameterised_segment_requires_its_value(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'by_country', 'message' => 'Hello'])
            ->assertSessionHasErrors('audience_value');

        $this->assertSame(0, SmsCampaign::count());
    }

    public function test_an_empty_audience_sends_nothing(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'Nobody home'])
            ->assertSessionHas('error');

        $this->assertSame(0, SmsCampaign::count());
        Queue::assertNotPushed(SendSmsCampaign::class);
    }

    public function test_sms_sending_is_blocked_while_the_master_switch_is_off(): void
    {
        config(['sms.enabled' => false]);
        Queue::fake();

        $this->registrant();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'Hello'])
            ->assertSessionHas('error');

        $this->assertSame(0, SmsCampaign::count());
        Queue::assertNotPushed(SendSmsCampaign::class);

        $this->actingAs(User::factory()->admin()->create(['country' => 'Tanzania', 'phone' => '0712345678']))
            ->post(route('admin.sms.test'), ['message' => 'Draft'])
            ->assertSessionHas('error');
    }

    /** A test send is a preview: it goes only to the admin's own number and leaves no campaign behind. */
    public function test_a_test_send_goes_only_to_the_admin_and_is_not_recorded(): void
    {
        $fake = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $fake);

        $this->registrant();
        $admin = User::factory()->admin()->create(['country' => 'Tanzania', 'phone' => '0787654321']);

        $this->actingAs($admin)
            ->post(route('admin.sms.test'), ['message' => 'Draft'])
            ->assertRedirect();

        $this->assertSame(['255787654321'], $fake->sentTo);
        $this->assertSame(0, SmsCampaign::count());
    }

    public function test_a_test_send_fails_cleanly_when_the_admin_has_no_usable_number(): void
    {
        $admin = User::factory()->admin()->create(['country' => 'Kenya', 'phone' => null]);

        $this->actingAs($admin)
            ->post(route('admin.sms.test'), ['message' => 'Draft'])
            ->assertSessionHas('error');
    }

    /** The test-send number can be overridden to check delivery to a handset other than the admin's own. */
    public function test_a_test_send_can_be_addressed_to_a_chosen_number(): void
    {
        $fake = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $fake);

        // The admin's own number is deliberately different from the typed-in
        // test number, so a pass here can't be explained by falling back to it.
        $admin = User::factory()->admin()->create(['country' => 'Tanzania', 'phone' => '0787654321']);

        $this->actingAs($admin)
            ->post(route('admin.sms.test'), ['message' => 'Draft', 'phone' => '0798765432'])
            ->assertRedirect();

        $this->assertSame(['255798765432'], $fake->sentTo);
        $this->assertSame(0, SmsCampaign::count());
    }

    public function test_a_test_send_rejects_an_unrecognisable_chosen_number(): void
    {
        $admin = User::factory()->admin()->create(['country' => 'Tanzania', 'phone' => '0712345678']);

        $this->actingAs($admin)
            ->post(route('admin.sms.test'), ['message' => 'Draft', 'phone' => 'not-a-number'])
            ->assertSessionHas('error');
    }

    public function test_the_sms_manager_is_admin_only(): void
    {
        $this->actingAs(User::factory()->reviewer()->create())
            ->get(route('admin.sms.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'S'])
            ->assertForbidden();
    }

    public function test_the_index_lists_previous_campaigns(): void
    {
        Queue::fake();
        $this->registrant();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.sms.store'), ['audience' => 'all', 'message' => 'Welcome']);

        $this->actingAs($admin)
            ->get(route('admin.sms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/sms/index')
                ->has('campaigns', 1)
                ->where('campaigns.0.message', 'Welcome')
                ->where('campaigns.0.audience_label', 'All registrants')
                ->has('segments')
                ->has('audienceOptions.countries')
            );
    }
}

/**
 * A gateway test double that never touches the network: the real SmsGateway
 * only throws on an unreachable/rejecting gateway, and sandbox mode (the
 * test default) silently logs and returns success for anything, so neither
 * can exercise the per-recipient failure path the job is meant to isolate.
 */
class FakeSmsGateway extends SmsGateway
{
    /** @var list<string> */
    public array $sentTo = [];

    public function __construct(private ?string $failFor = null) {}

    public function send(string $msisdn, string $message, array $context = []): void
    {
        if ($msisdn === $this->failFor) {
            throw new \RuntimeException('The SMS gateway rejected the message.');
        }

        $this->sentTo[] = $msisdn;
    }
}
