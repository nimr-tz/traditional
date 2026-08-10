<?php

namespace Tests\Feature;

use App\Jobs\SendSms;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsNotifier;
use App\Support\TanzanianPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sms.enabled', true);
        config()->set('sms.sandbox', false);
    }

    public function test_local_and_prefixed_tanzanian_numbers_normalize_to_the_same_msisdn(): void
    {
        foreach (['0712 345 678', '712345678', '+255 712 345 678', '255712345678'] as $input) {
            $this->assertSame('255712345678', TanzanianPhone::normalize($input, 'Tanzania'), $input);
        }
    }

    public function test_numbers_that_cannot_be_confirmed_tanzanian_are_rejected(): void
    {
        // A bare local number from a registrant abroad would otherwise be
        // prefixed with 255 and delivered to an unrelated Tanzanian subscriber.
        $this->assertNull(TanzanianPhone::normalize('0712345678', 'Kenya'));
        $this->assertNull(TanzanianPhone::normalize('+254712345678', 'Kenya'));
        $this->assertNull(TanzanianPhone::normalize('+1 202 555 0134', 'United States of America'));
        $this->assertNull(TanzanianPhone::normalize('0812345678', 'Tanzania'));
        $this->assertNull(TanzanianPhone::normalize('', 'Tanzania'));
        $this->assertNull(TanzanianPhone::normalize(null));
    }

    public function test_control_number_sms_carries_the_number_and_amount(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'phone' => '0712345678',
            'country' => 'Tanzania',
            'control_number' => '991234567890',
            'currency' => 'TZS',
            'fee_amount' => 150000,
        ]);

        app(SmsNotifier::class)->controlNumberIssued($user);

        Queue::assertPushed(SendSms::class, function (SendSms $job) {
            $this->assertLessThanOrEqual(160, strlen($job->message));

            return $job->msisdn === '255712345678'
                && str_contains($job->message, '991234567890')
                && str_contains($job->message, '150,000');
        });
    }

    public function test_registrants_without_a_tanzanian_number_are_skipped(): void
    {
        Queue::fake();

        $user = User::factory()->create(['phone' => '+44 20 7123 4567', 'country' => 'United Kingdom']);

        app(SmsNotifier::class)->registered($user);

        Queue::assertNothingPushed();
    }

    public function test_disabling_an_event_stops_only_that_message(): void
    {
        Queue::fake();
        config()->set('sms.events.registered', false);

        $user = User::factory()->create([
            'phone' => '0712345678',
            'country' => 'Tanzania',
            'control_number' => '991234567890',
        ]);

        app(SmsNotifier::class)->registered($user);
        Queue::assertNothingPushed();

        app(SmsNotifier::class)->controlNumberIssued($user);
        Queue::assertPushed(SendSms::class);
    }

    /**
     * Every template, rendered with the longest realistic value for each
     * placeholder. A message over 160 characters is billed as two, so this
     * guards the budget for templates that are edited later — an earlier
     * version of the rejection wording shipped at 163.
     */
    public function test_no_template_exceeds_one_sms_at_worst_case(): void
    {
        $worst = [
            ':name' => 'Nyambura-Wanjiru',
            ':url' => 'tmsc.apps.nimr.or.tz',
            ':title' => str_repeat('W', 30).'...',
            ':control_number' => '999999999999',
            ':currency' => 'TZS',
            ':amount' => '1,500,000',
        ];

        foreach (config('sms.templates') as $key => $template) {
            $rendered = strtr($template, $worst);

            $this->assertLessThanOrEqual(160, strlen($rendered), "Template [{$key}] is over one SMS part");
            $this->assertSame($rendered, Str::ascii($rendered), "Template [{$key}] contains non-GSM characters");
        }
    }

    /**
     * Abstract decision texts deliberately carry no registrant-supplied text.
     * A title with a Greek letter would switch the message to UCS-2 and drop
     * the limit from 160 to 70, so this fails if a title is ever put back in.
     */
    public function test_abstract_decision_sms_carries_no_registrant_supplied_text(): void
    {
        Queue::fake();

        $user = User::factory()->create(['phone' => '0712345678', 'country' => 'Tanzania']);
        $abstract = AbstractSubmission::create([
            'user_id' => $user->id,
            'subtheme_id' => Subtheme::create(['title' => 'Innovations', 'active' => true, 'sort_order' => 1])->id,
            'title' => 'β-glucan yield at 37°C from Aloe vera in the Southern Highlands',
            'authors' => [['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.',
            'objective' => 'Objective.',
            'methods' => 'Methods.',
            'results' => 'Results.',
            'conclusion' => 'Conclusion.',
            'presentation_type' => 'poster',
            'status' => 'accepted',
        ]);

        app(SmsNotifier::class)->abstractDecision($abstract->fresh('user'));

        Queue::assertPushed(SendSms::class, function (SendSms $job) {
            $this->assertSame($job->message, Str::ascii($job->message));
            $this->assertLessThanOrEqual(160, strlen($job->message));
            $this->assertStringNotContainsString('glucan', $job->message);

            return true;
        });
    }

    public function test_gateway_signs_the_exact_body_it_posts(): void
    {
        config()->set('sms.api_key', 'test-api-key');
        config()->set('sms.system_id', 'TZ-12345678');
        Http::fake([config('sms.url') => Http::response(['success' => true])]);

        app(SmsGateway::class)->send('255712345678', 'Test message');

        Http::assertSent(function ($request) {
            $expected = base64_encode(hash_hmac('sha256', $request->body(), 'test-api-key', true));

            return $request->header('hash')[0] === $expected
                && $request->header('sysId')[0] === 'TZ-12345678';
        });
    }

    public function test_a_rejected_message_fails_the_job_so_it_retries(): void
    {
        Http::fake([config('sms.url') => Http::response(['success' => false, 'statusCode' => '401'])]);

        $this->expectException(\RuntimeException::class);

        app(SmsGateway::class)->send('255712345678', 'Test message');
    }

    public function test_sandbox_mode_never_calls_the_gateway(): void
    {
        config()->set('sms.sandbox', true);
        Http::fake();

        app(SmsGateway::class)->send('255712345678', 'Test message');

        Http::assertNothingSent();
    }
}
