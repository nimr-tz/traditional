<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbstractReviewerDecision;
use App\Models\AbstractSubmission;
use App\Models\User;
use App\Support\BlindReview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $request->user()->isAdmin()
            ? $this->adminDashboard()
            : $this->reviewerDashboard($request->user());
    }

    /**
     * Full conference-operations view: registrations, payments, students, and
     * the global abstract queue. Only admin/super_admin have routes to act on
     * any of this, so only they should see it.
     */
    private function adminDashboard(): Response
    {
        $registrants = User::withRole(User::ROLE_USER);
        $students = User::query()
            ->withRole(User::ROLE_USER)
            ->whereIn('fee_category', ['student_east_africa', 'student_non_east_africa']);

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_registrants' => (clone $registrants)->count(),
                'paid' => (clone $registrants)->where('payment_status', 'verified')->count(),
                'pending_payment' => (clone $registrants)->where('payment_status', 'submitted')->count(),
                'checked_in' => (clone $registrants)->whereHas('attendance')->count(),
                'abstracts_total' => AbstractSubmission::count(),
                'abstracts_submitted' => AbstractSubmission::where('status', 'submitted')->count(),
                'abstracts_revision_requested' => AbstractSubmission::where('status', 'revision_requested')->count(),
                'abstracts_accepted' => AbstractSubmission::where('status', 'accepted')->count(),
                'abstracts_rejected' => AbstractSubmission::where('status', 'rejected')->count(),
                'students_pending' => (clone $students)->where('student_verification_status', 'pending')->count(),
            ],
            'reviewQueue' => AbstractSubmission::query()
                ->with(['user:id,name,salutation,email,institution', 'subtheme:id,title'])
                ->where('status', 'submitted')
                ->oldest('resubmitted_at')
                ->oldest('created_at')
                ->limit(6)
                ->get(),
            'studentQueue' => (clone $students)
                ->where('student_verification_status', 'pending')
                ->oldest()
                ->limit(5)
                ->get(['id', 'name', 'salutation', 'email', 'institution', 'fee_category', 'created_at']),
        ]);
    }

    /**
     * Plain reviewers only have routes for abstracts assigned to them
     * (routes/admin.php) — no registrations, payments, or student
     * verification — so their dashboard shows nothing beyond that.
     */
    private function reviewerDashboard(User $user): Response
    {
        $assigned = AbstractSubmission::query()->where(function ($query) use ($user) {
            $query->where('reviewer_one_id', $user->id)->orWhere('reviewer_two_id', $user->id);
        });

        // Scoped to the *current* round. Without that, a reviewer who asked for
        // a revision still counted as having decided, so the revised abstract
        // never came back to them — the one case review rounds exist for.
        $decidedByMeThisRound = fn ($query) => $query
            ->where('reviewer_id', $user->id)
            ->whereColumn('abstract_reviewer_decisions.round', 'abstract_submissions.review_round');

        $awaitingMyDecision = (clone $assigned)
            ->where('status', 'submitted')
            ->whereDoesntHave('reviewerDecisions', $decidedByMeThisRound);

        return Inertia::render('admin/reviewer-dashboard', [
            'stats' => [
                'assigned_total' => (clone $assigned)->count(),
                'awaiting_my_decision' => (clone $awaitingMyDecision)->count(),
                // Counts my own recorded recommendation for this round, so the
                // two together account for everything assigned to me.
                'reviewed_by_me' => (clone $assigned)->whereHas('reviewerDecisions', $decidedByMeThisRound)->count(),
                // My own recommendations, across every round — a record of what
                // I sent back. The old tile counted abstracts whose *current
                // status* was revision_requested, which was neither mine (a
                // co-reviewer's request counted too) nor durable: the moment the
                // author resubmitted, the status flipped to `submitted` and the
                // number silently dropped.
                'revisions_i_requested' => AbstractReviewerDecision::where('reviewer_id', $user->id)
                    ->where('recommendation', 'revision_requested')
                    ->count(),
                'acceptances_i_recommended' => AbstractReviewerDecision::where('reviewer_id', $user->id)
                    ->where('recommendation', 'accepted')
                    ->count(),
            ],
            // Author identity is stripped here as it is everywhere else a
            // reviewer can see an abstract. This queue used to eager-load the
            // author and hand it straight to the page — see App\Support\BlindReview.
            'reviewQueue' => (clone $awaitingMyDecision)
                ->with('subtheme:id,title')
                ->oldest('resubmitted_at')
                ->oldest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (AbstractSubmission $abstract) => BlindReview::redactSubmission($abstract->toArray(), $abstract))
                ->all(),
        ]);
    }
}
