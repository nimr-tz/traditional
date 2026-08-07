<?php

namespace App\Http\Controllers;

use App\Mail\AbstractReviewRequested;
use App\Mail\AbstractSubmitted;
use App\Models\AbstractReviewerComment;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AbstractController extends Controller
{
    /**
     * List the logged-in user's own abstract submissions.
     */
    public function index(): Response
    {
        $submissions = Auth::user()->abstractSubmissions()
            ->with('subtheme')
            ->latest()
            ->get();

        // The submission window reaches the page as a shared prop
        // (HandleInertiaRequests), so every page that renders the deadline
        // stops inviting submissions at the same moment the route guard does.
        return Inertia::render('abstracts/index', [
            'submissions' => $submissions,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('abstracts/create', [
            'subthemes' => Subtheme::where('active', true)->orderBy('sort_order')->get(['id', 'title']),
            'presentationTypes' => config('tmsc.presentation_types'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSubmission($request);

        $submission = DB::transaction(function () use ($data) {
            $submission = Auth::user()->abstractSubmissions()->create($data);

            $submission->reviewHistory()->create([
                'acted_by' => Auth::id(),
                'action' => 'submitted',
                'from_status' => null,
                'to_status' => 'submitted',
            ]);

            return $submission;
        });

        Mail::to(Auth::user()->email)->send(new AbstractSubmitted($submission));
        $this->notifyReviewers($submission);

        return to_route('abstracts.index')->with('success', 'Your abstract has been submitted.');
    }

    public function edit(AbstractSubmission $abstract): Response
    {
        $this->authorizeOwner($abstract);

        $abstract->load(['subtheme', 'reviewer:id,name', 'reviewHistory.actor:id,name', 'reviewerDecisions.comments']);

        // Comments from superseded rounds are kept now rather than deleted, so
        // each one carries the round it came from — otherwise feedback the
        // author already dealt with would reappear looking current.
        $currentRound = $abstract->review_round ?? 1;

        $reviewerComments = $abstract->reviewerDecisions
            ->sortBy('round')
            ->flatMap(fn ($decision) => $decision->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'section' => $comment->section,
                'body' => $comment->body,
                'addressed' => $comment->isAddressed(),
                'reviewer_label' => $abstract->reviewerLabelFor($decision->reviewer_id) ?? 'Reviewer',
                'round' => $decision->round,
                'is_current' => $decision->round === $currentRound,
                'created_at' => $comment->created_at,
            ]))
            ->values();

        return Inertia::render('abstracts/edit', [
            'submission' => $abstract,
            'subthemes' => Subtheme::where('active', true)->orderBy('sort_order')->get(['id', 'title']),
            'presentationTypes' => config('tmsc.presentation_types'),
            'reviewerComments' => $reviewerComments,
        ]);
    }

    public function update(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        $this->authorizeOwner($abstract);

        if (! in_array($abstract->status, ['submitted', 'revision_requested'], true)) {
            return back()->with('error', 'This abstract has already been reviewed and can no longer be edited.');
        }

        $isRevision = $abstract->status === 'revision_requested';
        $data = $this->validateSubmission($request);
        $reviewersToNotify = [];

        DB::transaction(function () use ($abstract, $data, $isRevision, &$reviewersToNotify) {
            $abstract->update(array_merge($data, $isRevision ? [
                'status' => 'submitted',
                'decision_notes' => null,
                'resubmitted_at' => now(),
                'decided_at' => null,
            ] : []));

            if ($isRevision) {
                $previousRound = $abstract->review_round ?? 1;
                $nextRound = $previousRound + 1;

                // Only the reviewer(s) who asked for a revision or rejected need
                // to look again. A reviewer who already accepted keeps their
                // recommendation and isn't asked twice.
                $reviewersToNotify = $abstract->reviewerDecisions()
                    ->where('round', $previousRound)
                    ->whereIn('recommendation', ['revision_requested', 'rejected'])
                    ->pluck('reviewer_id')
                    ->all();

                // An acceptance carries forward into the new round rather than
                // being re-asked...
                $abstract->reviewerDecisions()
                    ->where('round', $previousRound)
                    ->where('recommendation', 'accepted')
                    ->update(['round' => $nextRound]);

                // ...and everything else simply stays behind on the old round.
                // Nothing is deleted: the reviewer needs their previous comments
                // to judge whether the revision answered them, and the record of
                // why a revision was requested has to survive.
                $abstract->forceFill(['review_round' => $nextRound])->save();

                $abstract->reviewHistory()->create([
                    'acted_by' => Auth::id(),
                    'action' => 'resubmitted',
                    'from_status' => 'revision_requested',
                    'to_status' => 'submitted',
                ]);
            }
        });

        if ($isRevision) {
            Mail::to(Auth::user()->email)->send(new AbstractSubmitted($abstract->fresh(), true));
            $this->notifyAssignedReviewers($abstract->fresh(), $reviewersToNotify);

            return to_route('abstracts.index')->with('success', 'Your revised abstract has been resubmitted for review.');
        }

        return to_route('abstracts.index')->with('success', 'Your abstract has been updated.');
    }

    public function toggleCommentAddressed(AbstractSubmission $abstract, AbstractReviewerComment $comment): RedirectResponse
    {
        $this->authorizeOwner($abstract);
        abort_unless($comment->decision->abstract_submission_id === $abstract->id, 404);

        $comment->update(['addressed_at' => $comment->isAddressed() ? null : now()]);

        return back();
    }

    private function authorizeOwner(AbstractSubmission $abstract): void
    {
        abort_unless($abstract->user_id === Auth::id(), 403);
    }

    private function validateSubmission(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtheme_id' => ['required', Rule::exists('subthemes', 'id')->where('active', true)],
            'presentation_type' => ['required', Rule::in(array_keys(config('tmsc.presentation_types')))],
            'authors' => 'required|array|min:1',
            'authors.*.name' => 'required|string|max:255',
            'authors.*.institution' => 'required|string|max:255',
            'authors.*.is_presenter' => 'boolean',
            'background' => 'required|string',
            'objective' => 'required|string',
            'methods' => 'required|string',
            'results' => 'required|string',
            'conclusion' => 'required|string',
        ]);

        // The 300-word cap applies to all five sections combined, not per section.
        $wordCount = str_word_count(implode(' ', array_map(
            fn (string $section) => $data[$section],
            AbstractSubmission::SECTIONS,
        )));

        if ($wordCount > 300) {
            throw ValidationException::withMessages([
                'background' => 'The abstract must not exceed 300 words across Background, Objective, Methods, Results, and Conclusion combined.',
            ]);
        }

        return $data;
    }

    private function notifyReviewers(AbstractSubmission $submission, bool $isRevision = false): void
    {
        User::query()
            ->withRole(User::ABSTRACT_REVIEWER_ROLES)
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->send(new AbstractReviewRequested($submission, $isRevision)));
    }

    /**
     * Used on resubmission: only the reviewer(s) whose prior decision was
     * cleared (revision requested / rejected) get re-notified — a reviewer
     * who already accepted is not bothered again.
     */
    private function notifyAssignedReviewers(AbstractSubmission $submission, array $reviewerIds): void
    {
        User::query()
            ->whereIn('id', $reviewerIds)
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->send(new AbstractReviewRequested($submission, true)));
    }
}
