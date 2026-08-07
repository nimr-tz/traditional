<?php

namespace App\Providers;

use App\Support\ConferenceEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        /*
         * Registrants, reviewers, finance, and super admins all sign in through
         * the same public /login, and this system moves real GePG money — so the
         * floor is a little above Laravel's default 8 characters.
         *
         * `uncompromised()` is production-only: it calls out to the Have I Been
         * Pwned range API, which would make every test and local registration
         * depend on an outbound network call.
         */
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('checkin-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

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
