<?php

namespace App\Http\Controllers;

use App\Mail\AbstractReviewRequested;
use App\Mail\AbstractSubmitted;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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

        return Inertia::render('abstracts/edit', [
            'submission' => $abstract->load(['subtheme', 'reviewer:id,name', 'reviewHistory.actor:id,name']),
            'subthemes' => Subtheme::where('active', true)->orderBy('sort_order')->get(['id', 'title']),
            'presentationTypes' => config('tmsc.presentation_types'),
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

        DB::transaction(function () use ($abstract, $data, $isRevision) {
            $abstract->update(array_merge($data, $isRevision ? [
                'status' => 'submitted',
                'decision_notes' => null,
                'resubmitted_at' => now(),
                'decided_at' => null,
            ] : []));

            if ($isRevision) {
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
            $this->notifyReviewers($abstract->fresh(), true);

            return to_route('abstracts.index')->with('success', 'Your revised abstract has been resubmitted for review.');
        }

        return to_route('abstracts.index')->with('success', 'Your abstract has been updated.');
    }

    private function authorizeOwner(AbstractSubmission $abstract): void
    {
        abort_unless($abstract->user_id === Auth::id(), 403);
    }

    private function validateSubmission(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'subtheme_id' => ['required', Rule::exists('subthemes', 'id')->where('active', true)],
            'presentation_type' => ['required', Rule::in(array_keys(config('tmsc.presentation_types')))],
            'authors' => 'required|array|min:1',
            'authors.*.name' => 'required|string|max:255',
            'authors.*.institution' => 'required|string|max:255',
            'authors.*.is_presenter' => 'boolean',
            'abstract_text' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    if (str_word_count($value) > 300) {
                        $fail('The abstract must not exceed 300 words.');
                    }
                },
            ],
        ]);
    }

    private function notifyReviewers(AbstractSubmission $submission, bool $isRevision = false): void
    {
        User::query()
            ->where('is_admin', true)
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->send(new AbstractReviewRequested($submission, $isRevision)));
    }
}
