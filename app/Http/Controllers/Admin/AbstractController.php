<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AbstractDecision;
use App\Models\AbstractReviewerDecision;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use App\Services\Sms\SmsNotifier;
use App\Support\BlindReview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class AbstractController extends Controller
{
    /** Human labels for the `status` enum, shared between the list counts and this export. */
    private const STATUS_LABELS = [
        'submitted' => 'Awaiting review',
        'revision_requested' => 'Revision requested',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $reviewerOnly = ! $user->isAdmin();
        $scopeToReviewer = fn ($q) => $q->where(function ($q) use ($user) {
            $q->where('reviewer_one_id', $user->id)->orWhere('reviewer_two_id', $user->id);
        });

        $query = AbstractSubmission::with(['subtheme', 'reviewerOne:id,name', 'reviewerTwo:id,name'])
            // Blind review: the author relation is only loaded for admins, so a
            // reviewer's payload can never carry it (see App\Support\BlindReview).
            ->when(! $reviewerOnly, fn ($q) => $q->with('user'))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search, $reviewerOnly) {
                $q->where('title', 'like', "%{$search}%");

                // Searching by author name/email would let a reviewer probe for
                // the author of an abstract they're assigned to.
                if (! $reviewerOnly) {
                    $q->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
                }
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->subtheme_id, fn ($q, $id) => $q->where('subtheme_id', $id));

        $countsBase = AbstractSubmission::query()->when($reviewerOnly, $scopeToReviewer);

        $submissions = $query->latest()->paginate(20)->withQueryString();

        if ($reviewerOnly) {
            $submissions->through(fn (AbstractSubmission $abstract) => BlindReview::redactSubmission($abstract->toArray(), $abstract));
        }

        return Inertia::render('admin/abstracts/index', [
            'isBlinded' => $reviewerOnly,
            'submissions' => $submissions,
            'subthemes' => Subtheme::orderBy('sort_order')->get(['id', 'title']),
            'filters' => $request->only(['status', 'subtheme_id', 'search']),
            'counts' => [
                'total' => (clone $countsBase)->count(),
                'submitted' => (clone $countsBase)->where('status', 'submitted')->count(),
                'revision_requested' => (clone $countsBase)->where('status', 'revision_requested')->count(),
                'accepted' => (clone $countsBase)->where('status', 'accepted')->count(),
                'rejected' => (clone $countsBase)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * A proceedings-style PDF, grouped by subtheme, for the organizing
     * committee to print or circulate. Carries the same status/subtheme/search
     * filters as the abstracts list so exporting "what I'm currently looking
     * at" just works.
     *
     * PDF rather than a generated .docx: dompdf is already the app's document
     * pipeline (badges, certificates, receipts all go through it), and it
     * renders locally with no dependency on the recipient's Word install or
     * platform to open correctly.
     *
     * This exposes real author identity (never redacted), so — unlike index()
     * and show(), which are shared with blind reviewers — it stays admin-only
     * even though the route sits in the reviewer-shared middleware group (see
     * downloadPresentation() above for the same pattern).
     */
    public function exportPdf(Request $request): BaseResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_LABELS))],
            'subtheme_id' => ['nullable', 'integer', 'exists:subthemes,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $subthemes = Subtheme::query()
            ->orderBy('sort_order')
            ->when($data['subtheme_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->with(['abstractSubmissions' => function ($q) use ($data) {
                $q->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                    ->when($data['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($userQuery) => $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%"));
                    }))
                    ->orderBy('title');
            }])
            ->get()
            ->filter(fn (Subtheme $subtheme) => $subtheme->abstractSubmissions->isNotEmpty());

        $pdf = Pdf::loadView('pdf.abstracts', [
            'subthemes' => $subthemes,
            'statusLabel' => $data['status'] ?? null ? self::STATUS_LABELS[$data['status']] : null,
            'search' => $data['search'] ?? null,
            'statusLabels' => self::STATUS_LABELS,
        ])->setPaper('a4');

        return $pdf->download('tmsc-abstracts-'.now()->format('Y-m-d').'.pdf');
    }

    public function show(Request $request, AbstractSubmission $abstract): Response
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $abstract->isAssignedReviewer($user), 403);

        $isAdmin = $user->isAdmin();

        $abstract->load(array_filter([
            // Only admins get the author relation at all.
            $isAdmin ? 'user' : null,
            'subtheme',
            'reviewer:id,name,email',
            'reviewerOne:id,name,email',
            'reviewerTwo:id,name,email',
            'reviewerDecisions.reviewer:id,name,email',
            'reviewerDecisions.comments',
            'reviewHistory.actor:id,name,email',
        ]));

        $currentRound = $abstract->review_round ?? 1;
        $allDecisions = $abstract->reviewerDecisions;

        // Blind review, reviewer-to-reviewer: a reviewer may see THAT the other
        // assigned reviewer has decided, but never what they recommended or
        // wrote — only admins weigh both to make the final call.
        $decisions = $allDecisions
            ->where('round', $currentRound)
            ->map(fn ($decision) => $isAdmin || $decision->reviewer_id === $user->id
                ? $decision
                : [
                    'id' => $decision->id,
                    'reviewer_id' => $decision->reviewer_id,
                    'decided_at' => $decision->decided_at,
                    'reviewer' => $decision->reviewer,
                    'recommendation' => null,
                    'comments' => [],
                ])
            ->values();

        /*
         * Earlier rounds, so a re-review is informed by what was asked last
         * time. A reviewer sees only their OWN history — another reviewer's
         * past comments are as off-limits as their present ones. Admins see
         * every round from both reviewers.
         */
        $priorRounds = $allDecisions
            ->where('round', '<', $currentRound)
            ->when(! $isAdmin, fn ($rounds) => $rounds->where('reviewer_id', $user->id))
            ->sortByDesc('round')
            ->map(fn ($decision) => [
                'id' => $decision->id,
                'round' => $decision->round,
                'reviewer_id' => $decision->reviewer_id,
                'reviewer_name' => $isAdmin ? $decision->reviewer?->name : 'You',
                'recommendation' => $decision->recommendation,
                'decided_at' => $decision->decided_at,
                'comments' => $decision->comments,
            ])
            ->values();

        $submission = [
            ...$abstract->toArray(),
            'reviewer_decisions' => $decisions,
            'prior_rounds' => $priorRounds,
            'is_re_review' => $abstract->isReReview(),
            'completed_rounds' => $abstract->completedReviewRounds(),
        ];

        // Blind review, author-to-reviewer: strip every trace of who wrote it.
        if (BlindReview::appliesTo($user)) {
            $submission = BlindReview::redactSubmission($submission, $abstract);
        }

        return Inertia::render('admin/abstracts/show', [
            'submission' => $submission,
            'eligibleReviewers' => $isAdmin
                ? User::withRole(User::ABSTRACT_REVIEWER_ROLES)
                    ->where('id', '!=', $abstract->user_id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                : [],
        ]);
    }

    public function assignReviewers(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        $data = $request->validate([
            'reviewer_one_id' => ['required', 'integer', 'different:reviewer_two_id', Rule::exists('users', 'id')],
            'reviewer_two_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        foreach (['reviewer_one_id', 'reviewer_two_id'] as $field) {
            if ((int) $data[$field] === $abstract->user_id) {
                throw ValidationException::withMessages([$field => 'The author of this abstract cannot be assigned as a reviewer.']);
            }

            if (! User::find($data[$field])?->canReviewAbstracts()) {
                throw ValidationException::withMessages([$field => 'This user cannot be assigned as a reviewer.']);
            }
        }

        DB::transaction(function () use ($abstract, $data) {
            $keptReviewerIds = [(int) $data['reviewer_one_id'], (int) $data['reviewer_two_id']];

            // Drop the *current* recommendation of anyone no longer assigned, so
            // it can't count toward completion. Earlier rounds stay put — they
            // are the record of how the abstract got here.
            $abstract->currentReviewerDecisions()->whereNotIn('reviewer_id', $keptReviewerIds)->delete();

            $abstract->update([
                'reviewer_one_id' => $data['reviewer_one_id'],
                'reviewer_two_id' => $data['reviewer_two_id'],
            ]);
        });

        return back()->with('success', 'Reviewers assigned.');
    }

    public function recordReviewerDecision(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        $user = $request->user();
        abort_unless($abstract->isAssignedReviewer($user), 403, 'You are not an assigned reviewer for this abstract.');
        abort_unless($abstract->status === 'submitted', 422, 'This abstract is no longer awaiting review.');

        $data = $request->validate([
            'recommendation' => ['required', Rule::in(['accepted', 'revision_requested', 'rejected'])],
            'comments' => ['array'],
            'comments.*.section' => ['nullable', Rule::in(AbstractSubmission::SECTIONS)],
            'comments.*.body' => ['required', 'string', 'max:2000'],
        ]);

        $comments = $data['comments'] ?? [];

        if ($data['recommendation'] !== 'accepted' && count($comments) === 0) {
            throw ValidationException::withMessages([
                'comments' => 'At least one comment is required for this recommendation.',
            ]);
        }

        DB::transaction(function () use ($abstract, $user, $data, $comments) {
            // Keyed on the round too: a reviewer's recommendation from an
            // earlier round is history and must not be overwritten by this one.
            $decision = AbstractReviewerDecision::updateOrCreate(
                [
                    'abstract_submission_id' => $abstract->id,
                    'reviewer_id' => $user->id,
                    'round' => $abstract->review_round ?? 1,
                ],
                ['recommendation' => $data['recommendation'], 'decided_at' => now()],
            );

            $decision->comments()->delete();
            foreach ($comments as $comment) {
                $decision->comments()->create([
                    'section' => $comment['section'] ?? null,
                    'body' => $comment['body'],
                ]);
            }
        });

        // If both assigned reviewers now recommend acceptance, skip the
        // admin's manual decision step entirely — anything else (a reject,
        // a revision request, or a still-pending reviewer) is left for the
        // admin to weigh and decide on.
        if ($abstract->refresh()->bothReviewersDecided() && $this->bothReviewersAccepted($abstract)) {
            $this->finalizeDecision(
                $abstract,
                'accepted',
                'Automatically accepted — both assigned reviewers recommended acceptance.',
                null,
            );

            return back()->with('success', 'Your recommendation has been recorded. Both reviewers accepted, so the abstract has been automatically accepted.');
        }

        return back()->with('success', 'Your recommendation has been recorded.');
    }

    private function bothReviewersAccepted(AbstractSubmission $abstract): bool
    {
        // Current round only — a stale acceptance from before a revision must
        // not combine with a fresh one to trigger an automatic decision.
        return $abstract->currentReviewerDecisions()
            ->whereIn('reviewer_id', [$abstract->reviewer_one_id, $abstract->reviewer_two_id])
            ->pluck('recommendation')
            ->every(fn (string $recommendation) => $recommendation === 'accepted');
    }

    public function decide(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        abort_unless($abstract->status === 'submitted', 422, 'Only abstracts awaiting review can receive a decision.');
        abort_unless($abstract->hasReviewersAssigned(), 422, 'Assign two reviewers before making a decision.');
        abort_unless($abstract->bothReviewersDecided(), 422, 'Both assigned reviewers must submit their recommendation before a final decision can be made.');

        $data = $request->validate([
            'action' => ['required', Rule::in(['accepted', 'revision_requested', 'rejected'])],
            'decision_notes' => [
                Rule::requiredIf(fn () => in_array($request->input('action'), ['revision_requested', 'rejected'], true)),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $this->finalizeDecision($abstract, $data['action'], $data['decision_notes'] ?? null, Auth::id());

        $message = match ($data['action']) {
            'accepted' => 'Abstract accepted and the author has been notified.',
            'revision_requested' => 'Revision requested and the author has been notified.',
            'rejected' => 'Abstract permanently rejected and the author has been notified.',
        };

        return to_route('admin.abstracts.show', $abstract)->with('success', $message);
    }

    /**
     * Shared by the admin's manual decision and the automatic accept-on-consensus
     * path — $actorId is null for the latter, which the UI renders as "System".
     */
    private function finalizeDecision(AbstractSubmission $abstract, string $action, ?string $notes, ?int $actorId): void
    {
        DB::transaction(function () use ($abstract, $action, $notes, $actorId) {
            $now = now();

            $abstract->update([
                'status' => $action,
                'reviewer_id' => $actorId,
                'decision_notes' => $notes,
                'revision_requested_at' => $action === 'revision_requested' ? $now : $abstract->revision_requested_at,
                'decided_at' => in_array($action, ['accepted', 'rejected'], true) ? $now : null,
            ]);

            $abstract->reviewHistory()->create([
                'acted_by' => $actorId,
                'action' => $action,
                'from_status' => 'submitted',
                'to_status' => $action,
                'notes' => $notes,
            ]);
        });

        $abstract->load('user');
        $decided = $abstract->fresh(['user', 'subtheme', 'reviewer']);

        Mail::to($abstract->user->email)->send(new AbstractDecision($decided));
        app(SmsNotifier::class)->abstractDecision($decided);
    }

    public function downloadPresentation(Request $request, AbstractSubmission $abstract): StreamedResponse
    {
        // Presentation files are routinely named after their author, so the
        // download stays with admins rather than blind reviewers.
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($abstract->presentation_file, 404);

        return Storage::disk('local')->download($abstract->presentation_file, $abstract->presentation_original_name);
    }
}
