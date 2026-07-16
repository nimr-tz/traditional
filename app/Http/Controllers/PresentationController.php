<?php

namespace App\Http\Controllers;

use App\Mail\PresentationSubmittedForReview;
use App\Mail\PresentationUploaded;
use App\Models\AbstractSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresentationController extends Controller
{
    public function show(AbstractSubmission $abstract): Response
    {
        $this->authorizeOwner($abstract);

        abort_unless($abstract->status === 'accepted', 403, 'Presentation uploads open once your abstract has been accepted.');

        return Inertia::render('abstracts/presentation', [
            'submission' => array_merge(
                $abstract->only([
                    'id', 'title', 'presentation_type', 'presentation_status',
                    'presentation_original_name', 'presentation_uploaded_at',
                    'presentation_notes', 'presentation_review_notes',
                ]),
                ['can_upload' => $abstract->canUploadPresentation()],
            ),
            'requirements' => $abstract->presentationRequirements(),
        ]);
    }

    public function store(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        $this->authorizeOwner($abstract);
        abort_unless($abstract->canUploadPresentation(), 422, 'Presentations can no longer be uploaded for this abstract.');

        $requirements = $abstract->presentationRequirements();

        $data = $request->validate([
            'presentation_file' => [
                'required', 'file',
                'extensions:'.implode(',', $requirements['extensions']),
                'max:'.$requirements['max_kb'],
            ],
            'presentation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $isReplacement = (bool) $abstract->presentation_file;
        $oldPath = $abstract->presentation_file;
        $file = $request->file('presentation_file');
        $newPath = $file->store('presentations', 'local');

        $abstract->forceFill([
            'presentation_file' => $newPath,
            'presentation_original_name' => $file->getClientOriginalName(),
            'presentation_uploaded_at' => now(),
            'presentation_notes' => $data['presentation_notes'] ?? null,
            'presentation_status' => 'uploaded',
            'presentation_review_notes' => null,
        ])->save();

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        Mail::to($abstract->user->email)->send(new PresentationUploaded($abstract, $isReplacement));

        User::query()
            ->whereIn('role', User::ABSTRACT_REVIEWER_ROLES)
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->send(new PresentationSubmittedForReview($abstract, $isReplacement)));

        return back()->with('success', 'Your presentation file has been submitted.');
    }

    public function destroy(AbstractSubmission $abstract): RedirectResponse
    {
        $this->authorizeOwner($abstract);
        abort_unless($abstract->canUploadPresentation() && $abstract->presentation_file, 422);

        Storage::disk('local')->delete($abstract->presentation_file);

        $abstract->forceFill([
            'presentation_file' => null,
            'presentation_original_name' => null,
            'presentation_uploaded_at' => null,
            'presentation_status' => 'pending',
        ])->save();

        return back()->with('success', 'Your presentation file has been removed.');
    }

    public function download(AbstractSubmission $abstract): StreamedResponse
    {
        $user = Auth::user();

        abort_unless($abstract->user_id === $user->id || $user->canReviewAbstracts(), 403);
        abort_unless($abstract->presentation_file, 404);

        return Storage::disk('local')->download($abstract->presentation_file, $abstract->presentation_original_name);
    }

    private function authorizeOwner(AbstractSubmission $abstract): void
    {
        abort_unless($abstract->user_id === Auth::id(), 403);
    }
}
