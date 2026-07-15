<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_verification_email_uses_the_conference_design_and_copy()
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Test Participant',
            'salutation' => 'Dr.',
            'email' => 'participant@example.com',
        ]);

        $mail = (new VerifyEmail)->toMail($user);
        $html = (string) $mail->render();
        $text = view('emails.verify-email-text', $mail->viewData)->render();

        $this->assertSame('Confirm your email address', $mail->subject);
        $this->assertStringContainsString('Dear Dr. Test Participant', $html);
        $this->assertStringContainsString('participant@example.com', $html);
        $this->assertStringContainsString('Verify email address', $html);
        $this->assertStringContainsString('continue your registration', $html);
        $this->assertStringContainsString('&signature=', $text);
        $this->assertStringNotContainsString('&amp;signature=', $text);
    }

    public function test_email_can_be_verified()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
