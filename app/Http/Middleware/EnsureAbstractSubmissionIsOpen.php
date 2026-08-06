<?php

namespace App\Http\Middleware;

use App\Support\SubmissionWindow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks *new* abstract submissions once the call for abstracts has closed.
 *
 * Applied to both the create form and its POST target, so a form left open in a
 * browser when the deadline passed can't still create a submission.
 *
 * Deliberately not applied to abstracts.edit/update: reviewers request revisions
 * long after submissions close, and those revisions have to be answerable. The
 * deadline is a door for new work, not a freeze on work already in review.
 *
 * Unlike EnsureRegistrationIsOpen this redirects rather than rendering a
 * dedicated page — these routes are behind auth, so the author has somewhere to
 * land (their own submissions list) and needs a sentence, not a landing page.
 */
class EnsureAbstractSubmissionIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SubmissionWindow::isOpen()) {
            return $next($request);
        }

        return to_route('abstracts.index')->with('error', SubmissionWindow::closedMessage());
    }
}
