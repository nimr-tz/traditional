<?php

namespace App\Services;

use App\Mail\CriticalErrorAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CriticalErrorAlertService
{
    private static bool $dispatching = false;

    public function handle(Throwable $exception, ?Request $request = null): void
    {
        if (! $this->shouldNotify($exception)) {
            return;
        }

        $context = $this->context($exception, $request);
        $fingerprint = hash('sha256', implode('|', [
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            (string) $exception->getLine(),
            $request?->route()?->getName() ?? $request?->path() ?? 'console',
        ]));

        try {
            $isNew = Cache::add(
                "critical-error-alert:{$fingerprint}",
                now()->toIso8601String(),
                now()->addMinutes(max(1, (int) config('mail.error_alerts.dedupe_minutes', 15))),
            );
        } catch (Throwable $cacheException) {
            $isNew = true;
            Log::warning('Critical error alert deduplication failed', [
                'exception' => $cacheException::class,
                'message' => $cacheException->getMessage(),
            ]);
        }

        if (! $isNew) {
            return;
        }

        Log::critical('TMSC runtime exception', $context);

        $recipients = config('mail.error_alerts.to', []);
        if ($recipients === []) {
            return;
        }

        try {
            self::$dispatching = true;
            Mail::mailer((string) config('mail.error_alerts.mailer', config('mail.default')))
                ->to($recipients)
                ->send(new CriticalErrorAlert($context));
        } catch (Throwable $mailException) {
            Log::error('Critical error alert delivery failed', [
                'original_exception' => $exception::class,
                'mail_exception' => $mailException::class,
                'mail_message' => $mailException->getMessage(),
            ]);
        } finally {
            self::$dispatching = false;
        }
    }

    private function shouldNotify(Throwable $exception): bool
    {
        if (! config('mail.error_alerts.enabled') || self::$dispatching) {
            return false;
        }

        if ($exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException
            || $exception instanceof ModelNotFoundException
            || $exception instanceof TokenMismatchException) {
            return false;
        }

        return ! ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500);
    }

    private function context(Throwable $exception, ?Request $request): array
    {
        $user = $request?->user();

        return [
            'app' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'url' => config('app.url'),
                'reported_at' => now()->toIso8601String(),
            ],
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())
                    ->take(12)
                    ->map(fn (array $frame) => [
                        'file' => $frame['file'] ?? null,
                        'line' => $frame['line'] ?? null,
                        'class' => $frame['class'] ?? null,
                        'function' => $frame['function'] ?? null,
                    ])
                    ->all(),
            ],
            'request' => $request ? [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ] : null,
            'user' => $user ? [
                'id' => $user->getAuthIdentifier(),
                'email' => $user->email,
            ] : null,
        ];
    }
}
