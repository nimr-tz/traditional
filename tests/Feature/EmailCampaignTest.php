<?php

namespace Tests\Feature;

use App\Jobs\SendEmailCampaign;
use App\Mail\CampaignMessage;
use App\Models\AbstractSubmission;
use App\Models\EmailCampaign;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function registrant(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'country' => 'Tanzania',
            'institution' => 'NIMR',
            'payment_status' => 'pending',
        ], $overrides));
    }

    private function abstractFor(User $user, string $status = 'submitted'): void
    {
        $subtheme = Subtheme::firstOrCreate(['title' => 'Innovations'], ['active' => true, 'sort_order' => 1]);

        AbstractSubmission::create([
            'user_id' => $user->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'An abstract',
            'authors' => [['name' => 'A', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'B.', 'objective' => 'O.', 'methods' => 'M.', 'results' => 'R.', 'conclusion' => 'C.',
            'presentation_type' => 'oral',
            'status' => $status,
        ]);
    }

    public function test_a_campaign_queues_one_recipient_row_per_person_in_the_segment(): void
    {
        Queue::fake();

        $this->registrant(['payment_status' => 'verified']);
        $this->registrant(['payment_status' => 'waived']);
        $this->registrant(['payment_status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), [
                'audience' => 'paid',
                'subject' => 'See you in Mbeya',
                'body' => "First paragraph.\n\nSecond paragraph.",
            ])
            ->assertRedirect();

        $campaign = EmailCampaign::firstOrFail();

        $this->assertSame('paid', $campaign->audience);
        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame('queued', $campaign->status);
        $this->assertCount(2, $campaign->recipients);
        Queue::assertPushed(SendEmailCampaign::class);
    }

    /** Announcements must never reach the staff/reviewer/admin accounts sharing the users table. */
    public function test_campaigns_never_target_non_participant_accounts(): void
    {
        Queue::fake();

        $this->registrant();
        User::factory()->reviewer()->create();
        User::factory()->superAdmin()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), [
                'audience' => 'all',
                'subject' => 'Notice',
                'body' => 'Body.',
            ]);

        $this->assertSame(1, EmailCampaign::firstOrFail()->recipient_count);
    }

    public function test_running_the_job_delivers_and_records_each_recipient(): void
    {
        Mail::fake();

        $this->registrant();
        $this->registrant();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), [
                'audience' => 'all',
                'subject' => 'Programme published',
                'body' => 'The programme is now available.',
            ]);

        $campaign = EmailCampaign::firstOrFail();
        (new SendEmailCampaign($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertNotNull($campaign->completed_at);
        $this->assertCount(2, $campaign->recipients()->where('status', 'sent')->get());

        Mail::assertSent(CampaignMessage::class, 2);
    }

    /**
     * The recipient list is frozen when the campaign is created. If it were
     * recomputed at delivery time, someone who paid in between would silently
     * drop out of a send the admin was told covered them.
     */
    public function test_the_recipient_list_is_frozen_at_creation_not_at_delivery(): void
    {
        Mail::fake();

        $willPay = $this->registrant(['payment_status' => 'pending']);
        $this->registrant(['payment_status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), [
                'audience' => 'unpaid',
                'subject' => 'Payment reminder',
                'body' => 'Please pay.',
            ]);

        $willPay->forceFill(['payment_status' => 'verified'])->save();

        $campaign = EmailCampaign::firstOrFail();
        (new SendEmailCampaign($campaign->id))->handle();

        $this->assertSame(2, $campaign->refresh()->sent_count);
        Mail::assertSent(CampaignMessage::class, fn ($mail) => $mail->hasTo($willPay->email));
    }

    public function test_segments_resolve_to_the_right_people(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $author = $this->registrant();
        $this->abstractFor($author, 'accepted');
        $plain = $this->registrant();

        $expectations = [
            'all' => 2,
            'abstract_authors' => 1,
            'accepted_authors' => 1,
            'no_abstract' => 1,
            'by_country' => 2,
        ];

        foreach ($expectations as $segment => $expected) {
            $params = ['audience' => $segment];
            if ($segment === 'by_country') {
                $params['audience_value'] = 'Tanzania';
            }

            $response = $this->actingAs($admin)->getJson(route('admin.emails.count', $params));
            $response->assertOk();
            $this->assertSame($expected, $response->json('count'), "Segment '{$segment}' resolved to the wrong count.");
        }

        $this->assertNotNull($plain);
    }

    public function test_a_parameterised_segment_requires_its_value(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), [
                'audience' => 'by_country',
                'subject' => 'Hello',
                'body' => 'Body.',
            ])
            ->assertSessionHasErrors('audience_value');

        $this->assertSame(0, EmailCampaign::count());
    }

    public function test_an_empty_audience_sends_nothing(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), [
                'audience' => 'all',
                'subject' => 'Nobody home',
                'body' => 'Body.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, EmailCampaign::count());
        Queue::assertNotPushed(SendEmailCampaign::class);
    }

    /** A test send is a preview: it goes only to the admin and leaves no campaign behind. */
    public function test_a_test_send_goes_only_to_the_admin_and_is_not_recorded(): void
    {
        Mail::fake();

        $this->registrant();
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.emails.test'), ['subject' => 'Draft', 'body' => 'Draft body.'])
            ->assertRedirect();

        Mail::assertSent(CampaignMessage::class, 1);
        Mail::assertSent(CampaignMessage::class, fn ($mail) => $mail->hasTo('admin@example.com'));
        $this->assertSame(0, EmailCampaign::count());
    }

    public function test_a_failed_address_does_not_abort_the_rest_of_the_campaign(): void
    {
        Queue::fake();

        $this->registrant();
        $this->registrant();
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.emails.store'), ['audience' => 'all', 'subject' => 'S', 'body' => 'B']);

        $campaign = EmailCampaign::firstOrFail();
        // Deliberately NOT Mail::fake() here: the fake accepts anything, so it
        // could never exercise the failure path. The array mailer builds a real
        // message, and an empty To address throws on send the way a malformed
        // address would in production.
        $campaign->recipients()->first()->forceFill(['email' => ''])->save();

        (new SendEmailCampaign($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertNotNull($campaign->recipients()->where('status', 'failed')->first()->error);
    }

    public function test_the_email_manager_is_admin_only(): void
    {
        $this->actingAs(User::factory()->reviewer()->create())
            ->get(route('admin.emails.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.emails.store'), ['audience' => 'all', 'subject' => 'S', 'body' => 'B'])
            ->assertForbidden();
    }

    public function test_the_index_lists_previous_campaigns(): void
    {
        Queue::fake();
        $this->registrant();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.emails.store'), [
            'audience' => 'all', 'subject' => 'Welcome', 'body' => 'Body.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.emails.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/emails/index')
                ->has('campaigns', 1)
                ->where('campaigns.0.subject', 'Welcome')
                ->where('campaigns.0.audience_label', 'All registrants')
                ->has('segments')
                ->has('audienceOptions.countries')
            );
    }
}
