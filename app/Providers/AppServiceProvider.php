<?php

namespace App\Providers;

use App\Support\ConferenceEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            $emailAddress = $notifiable->getEmailForVerification();
            $expiryMinutes = (int) config('auth.verification.expire', 60);
            $data = array_merge(ConferenceEmail::data($notifiable), compact(
                'emailAddress',
                'expiryMinutes',
                'verificationUrl',
            ));

            return (new MailMessage)
                ->subject('Confirm your email address')
                ->view([
                    'html' => 'emails.verify-email',
                    'text' => 'emails.verify-email-text',
                ], $data);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $emailAddress = $notifiable->getEmailForPasswordReset();
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $emailAddress,
            ], false));
            $expiryMinutes = (int) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60,
            );
            $data = array_merge(ConferenceEmail::data($notifiable), compact(
                'emailAddress',
                'expiryMinutes',
                'resetUrl',
            ));

            return (new MailMessage)
                ->subject('Reset your password')
                ->view([
                    'html' => 'emails.reset-password',
                    'text' => 'emails.reset-password-text',
                ], $data);
        });
    }
}
