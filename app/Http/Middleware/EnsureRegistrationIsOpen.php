<?php

namespace App\Http\Middleware;

use App\Models\ConferenceSetting;
use App\Support\RegistrationWindow;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks new registrations once the registration window has closed.
 *
 * Applied to both the register form and its POST target, so a form that was
 * already open in a browser when the deadline passed can't still create an
 * account. A closed GET renders the "registration closed" page rather than
 * 403-ing, since this is a public marketing-facing URL and the visitor needs
 * to be told what happened and who to contact.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (RegistrationWindow::isOpen()) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return Inertia::render('auth/registration-closed', [
                'closedMessage' => RegistrationWindow::closedMessage(),
                'contactEmail' => ConferenceSetting::get('contact_email'),
            ])->toResponse($request)->setStatusCode(403);
        }

        return redirect()->route('register');
    }
}
