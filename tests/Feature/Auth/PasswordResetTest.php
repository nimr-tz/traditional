<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_email_uses_the_conference_design()
    {
        $user = User::factory()->create([
            'name' => 'Test Participant',
            'salutation' => 'Dr.',
            'email' => 'participant@example.com',
        ]);

        $mail = (new ResetPassword('sample-token'))->toMail($user);
        $html = (string) $mail->render();
        $text = view('emails.reset-password-text', $mail->viewData)->render();

        $this->assertSame('Reset your password', $mail->subject);
        $this->assertStringContainsString('alt="NIMR"', $html);
        $this->assertStringContainsString('Dear Dr. Test Participant', $html);
        $this->assertStringContainsString('Reset my password', $html);
        $this->assertStringContainsString('sample-token', $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Password1234',
                'password_confirmation' => 'Password1234',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
