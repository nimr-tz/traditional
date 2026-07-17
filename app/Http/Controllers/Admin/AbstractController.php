<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AbstractDecision;
use App\Mail\PresentationApproved;
use App\Mail\PresentationRejected;
use App\Models\AbstractReviewerDecision;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbstractController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $reviewerOnly = ! $user->isAdmin();
        $scopeToReviewer = fn ($q) => $q->where(function ($q) use ($user) {
            $q->where('reviewer_one_id', $user->id)->orWhere('reviewer_two_id', $user->id);
        });

        $query = AbstractSubmission::with(['user', 'subtheme', 'reviewerOne:id,name', 'reviewerTwo:id,name'])
            ->when($reviewerOnly, $scopeToReviewer)
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->subtheme_id, fn ($q, $id) => $q->where('subtheme_id', $id));

        $countsBase = AbstractSubmission::query()->when($reviewerOnly, $scopeToReviewer);

        return Inertia::render('admin/abstracts/index', [
            'submissions' => $query->latest()->paginate(20)->withQueryString(),
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

    public function show(Request $request, AbstractSubmission $abstract): Response
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $abstract->isAssignedReviewer($user), 403);

        $abstract->load([
            'user',
            'subtheme',
            'reviewer:id,name,email',
            'reviewerOne:id,name,email',
            'reviewerTwo:id,name,email',
            'reviewerDecisions.reviewer:id,name,email',
            'reviewerDecisions.comments',
            'reviewHistory.actor:id,name,email',
        ]);

        // Blind review: a reviewer may see THAT the other assigned reviewer has
        // decided, but never what they recommended or wrote — only admins (who
        // weigh both to make the final call) get the full recommendation/comments.
        $isAdmin = $user->isAdmin();
        $decisions = $abstract->reviewerDecisions->map(fn ($decision) => $isAdmin || $decision->reviewer_id === $user->id
            ? $decision
            : [
                'id' => $decision->id,
                'reviewer_id' => $decision->reviewer_id,
                'decided_at' => $decision->decided_at,
                'reviewer' => $decision->reviewer,
                'recommendation' => null,
                'comments' => [],
            ]);

        return Inertia::render('admin/abstracts/show', [
            'submission' => [
                ...$abstract->toArray(),
                'reviewer_decisions' => $decisions,
            ],
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

            // Drop any recommendation left behind by a reviewer who is no longer assigned.
            $abstract->reviewerDecisions()->whereNotIn('reviewer_id', $keptReviewerIds)->delete();

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
            $decision = AbstractReviewerDecision::updateOrCreate(
                ['abstract_submission_id' => $abstract->id, 'reviewer_id' => $user->id],
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
        return $abstract->reviewerDecisions()
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
        Mail::to($abstract->user->email)->send(new AbstractDecision($abstract->fresh(['user', 'subtheme', 'reviewer'])));
    }

    public function downloadPresentation(AbstractSubmission $abstract): StreamedResponse
    {
        abort_unless($abstract->presentation_file, 404);

        return Storage::disk('local')->download($abstract->presentation_file, $abstract->presentation_original_name);
    }

    public function approvePresentation(AbstractSubmission $abstract): RedirectResponse
    {
        abort_unless($abstract->presentation_status === 'uploaded', 422, 'Only an uploaded presentation can be approved.');

        $abstract->forceFill(['presentation_status' => 'approved', 'presentation_review_notes' => null])->save();

        Mail::to($abstract->user->email)->send(new PresentationApproved($abstract));

        return back()->with('success', 'Presentation approved.');
    }

    public function rejectPresentation(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        abort_unless($abstract->presentation_status === 'uploaded', 422, 'Only an uploaded presentation can be rejected.');

        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);

        $abstract->forceFill([
            'presentation_status' => 'pending',
            'presentation_review_notes' => $data['notes'],
        ])->save();

        Mail::to($abstract->user->email)->send(new PresentationRejected($abstract->fresh()));

        return back()->with('success', 'Presentation rejected — the presenter has been notified.');
    }
}
