<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbstractReviewerDecision;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cross-cutting view of abstract review.
 *
 * Assignment itself already lives on each abstract's own page, but that only
 * ever answers "who reviews *this* one". The questions an admin actually has to
 * answer while the review window is open are pipeline-wide — what still has no
 * reviewers, who is sitting on work, and what is ready for a final decision —
 * and none of those are visible one abstract at a time. This page answers them,
 * and lets the admin assign straight from the list via the existing
 * AbstractController@assignReviewers endpoint.
 */
class ReviewerAssignmentController extends Controller
{
    /**
     * Where an abstract sits in the review pipeline. These are derived from
     * assignment + decision state rather than stored, so they can never drift
     * out of sync with the data they describe.
     */
    private const STAGES = ['unassigned', 'awaiting_reviews', 'ready_for_decision', 'decided'];

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'stage' => ['nullable', 'string', 'in:'.implode(',', self::STAGES)],
            'subtheme_id' => ['nullable', 'integer'],
            'reviewer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = AbstractSubmission::query()
            ->with(['user:id,name,salutation,email', 'subtheme:id,title', 'reviewerOne:id,name,salutation', 'reviewerTwo:id,name,salutation'])
            ->withCount('reviewerDecisions')
            ->with(['reviewerDecisions:id,abstract_submission_id,reviewer_id,decided_at'])
            ->when($data['stage'] ?? null, fn (Builder $q, string $stage) => $this->scopeToStage($q, $stage))
            ->when($data['subtheme_id'] ?? null, fn (Builder $q, int $id) => $q->where('subtheme_id', $id))
            ->when($data['reviewer_id'] ?? null, fn (Builder $q, int $id) => $q->where(
                fn (Builder $q) => $q->where('reviewer_one_id', $id)->orWhere('reviewer_two_id', $id)
            ))
            ->when($data['search'] ?? null, fn (Builder $q, string $search) => $q->where(
                fn (Builder $q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
            ));

        // Unassigned first, then oldest submission first: the queue an admin
        // works top-down, rather than newest-first like the browse listing.
        $submissions = $query
            ->orderByRaw('(reviewer_one_id is null or reviewer_two_id is null) desc')
            ->oldest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AbstractSubmission $abstract) => [
                'id' => $abstract->id,
                'title' => $abstract->title,
                'status' => $abstract->status,
                'created_at' => $abstract->created_at,
                'author' => $abstract->user?->only(['id', 'name', 'full_name', 'email']),
                'subtheme' => $abstract->subtheme?->only(['id', 'title']),
                'reviewer_one' => $abstract->reviewerOne?->only(['id', 'name', 'full_name']),
                'reviewer_two' => $abstract->reviewerTwo?->only(['id', 'name', 'full_name']),
                'decided_reviewer_ids' => $abstract->reviewerDecisions->pluck('reviewer_id'),
                'decisions_count' => $abstract->reviewer_decisions_count,
                'stage' => $this->stageOf($abstract),
            ]);

        return Inertia::render('admin/assignments/index', [
            'submissions' => $submissions,
            'filters' => $data,
            'stats' => $this->stageCounts(),
            'reviewers' => $this->reviewerWorkload(),
            'subthemes' => Subtheme::orderBy('sort_order')->get(['id', 'title']),
        ]);
    }

    /**
     * The stages are mutually exclusive and must stay in step with stageOf():
     * a decided abstract is 'decided' even though it may never have had
     * reviewers, so every open-pipeline stage is scoped to status=submitted.
     * Without that, an abstract rejected before assignment would be counted
     * twice — once as decided, once as needing reviewers it will never need.
     */
    private function scopeToStage(Builder $query, string $stage): Builder
    {
        if ($stage === 'decided') {
            return $query->where('status', '!=', 'submitted');
        }

        $query->where('status', 'submitted');

        return match ($stage) {
            'unassigned' => $query->where(
                fn (Builder $q) => $q->whereNull('reviewer_one_id')->orWhereNull('reviewer_two_id')
            ),
            'awaiting_reviews' => $query
                ->whereNotNull('reviewer_one_id')->whereNotNull('reviewer_two_id')
                ->has('reviewerDecisions', '<', 2),
            'ready_for_decision' => $query
                ->whereNotNull('reviewer_one_id')->whereNotNull('reviewer_two_id')
                ->has('reviewerDecisions', '>=', 2),
        };
    }

    private function stageOf(AbstractSubmission $abstract): string
    {
        if ($abstract->status !== 'submitted') {
            return 'decided';
        }

        if (! $abstract->hasReviewersAssigned()) {
            return 'unassigned';
        }

        return $abstract->reviewer_decisions_count >= 2 ? 'ready_for_decision' : 'awaiting_reviews';
    }

    /** @return array<string, int> */
    private function stageCounts(): array
    {
        $counts = ['total' => AbstractSubmission::count()];

        foreach (self::STAGES as $stage) {
            $counts[$stage] = $this->scopeToStage(AbstractSubmission::query(), $stage)->count();
        }

        return $counts;
    }

    /**
     * Per-reviewer load, so an admin can see who is over-committed before
     * handing out more work — and who has abstracts sitting unreviewed.
     *
     * `awaiting_count` is the number that actually needs action from them: still
     * open for review, assigned to them, and with no recommendation from them
     * yet. Correlated subqueries rather than an N+1 loop, since this renders on
     * every page load.
     */
    private function reviewerWorkload(): Collection
    {
        $assignedToReviewer = fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->whereColumn('abstract_submissions.reviewer_one_id', 'users.id')
                ->orWhereColumn('abstract_submissions.reviewer_two_id', 'users.id')
        );

        return User::withRole(User::ABSTRACT_REVIEWER_ROLES)
            ->select(['id', 'name', 'salutation', 'email', 'role'])
            ->selectSub(
                $assignedToReviewer(AbstractSubmission::query())->selectRaw('count(*)'),
                'assigned_count'
            )
            ->selectSub(
                $assignedToReviewer(AbstractSubmission::query())
                    ->where('abstract_submissions.status', 'submitted')
                    ->whereNotExists(fn ($q) => $q
                        ->select(DB::raw(1))
                        ->from('abstract_reviewer_decisions')
                        ->whereColumn('abstract_reviewer_decisions.abstract_submission_id', 'abstract_submissions.id')
                        ->whereColumn('abstract_reviewer_decisions.reviewer_id', 'users.id'))
                    ->selectRaw('count(*)'),
                'awaiting_count'
            )
            ->selectSub(
                AbstractReviewerDecision::query()
                    ->whereColumn('abstract_reviewer_decisions.reviewer_id', 'users.id')
                    ->selectRaw('count(*)'),
                'decisions_count'
            )
            ->orderBy('name')
            ->get()
            ->map(fn (User $reviewer) => [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
                'full_name' => $reviewer->full_name,
                'email' => $reviewer->email,
                'assigned_count' => (int) $reviewer->assigned_count,
                'awaiting_count' => (int) $reviewer->awaiting_count,
                'decisions_count' => (int) $reviewer->decisions_count,
            ]);
    }
}
