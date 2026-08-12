<?php

namespace App\Http\Controllers;

use App\Services\BadgePrinter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * A registrant downloading their own badge.
 *
 * This controller existed with no route registered, so nobody — registrant or
 * staff — could ever obtain a badge, while the landing page invited people to
 * "complete your payment and download your badge". It is wired up now.
 */
class BadgeController extends Controller
{
    public function download(BadgePrinter $printer): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user->canPrintBadge()) {
            return redirect()
                ->route('payment.show')
                ->with('error', 'Your badge becomes available once your payment is confirmed.');
        }

        return $printer->render($user, $user)->download($printer->filenameFor($user));
    }
}
