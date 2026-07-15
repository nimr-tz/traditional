<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AbstractDecision;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
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
    public function index(Request $request): Response
    {
        $query = AbstractSubmission::with(['user', 'subtheme'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->subtheme_id, fn ($q, $id) => $q->where('subtheme_id', $id));

        return Inertia::render('admin/abstracts/index', [
            'submissions' => $query->latest()->paginate(20)->withQueryString(),
            'subthemes' => Subtheme::orderBy('sort_order')->get(['id', 'title']),
            'filters' => $request->only(['status', 'subtheme_id', 'search']),
            'counts' => [
                'submitted' => AbstractSubmission::where('status', 'submitted')->count(),
                'revision_requested' => AbstractSubmission::where('status', 'revision_requested')->count(),
                'accepted' => AbstractSubmission::where('status', 'accepted')->count(),
                'rejected' => AbstractSubmission::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(AbstractSubmission $abstract): Response
    {
        $abstract->load([
            'user',
            'subtheme',
            'reviewer:id,name,email',
            'reviewHistory.actor:id,name,email',
        ]);

        return Inertia::render('admin/abstracts/show', [
            'submission' => $abstract,
        ]);
    }

    public function decide(Request $request, AbstractSubmission $abstract): RedirectResponse
    {
        abort_unless($abstract->status === 'submitted', 422, 'Only abstracts awaiting review can receive a decision.');

        $data = $request->validate([
            'action' => ['required', Rule::in(['accepted', 'revision_requested', 'rejected'])],
            'decision_notes' => [
                Rule::requiredIf(fn () => in_array($request->input('action'), ['revision_requested', 'rejected'], true)),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use ($abstract, $data) {
            $action = $data['action'];
            $now = now();

            $abstract->update([
                'status' => $action,
                'reviewer_id' => Auth::id(),
                'decision_notes' => $data['decision_notes'] ?? null,
                'revision_requested_at' => $action === 'revision_requested' ? $now : $abstract->revision_requested_at,
                'decided_at' => in_array($action, ['accepted', 'rejected'], true) ? $now : null,
            ]);

            $abstract->reviewHistory()->create([
                'acted_by' => Auth::id(),
                'action' => $action,
                'from_status' => 'submitted',
                'to_status' => $action,
                'notes' => $data['decision_notes'] ?? null,
            ]);
        });

        $abstract->load('user');
        Mail::to($abstract->user->email)->send(new AbstractDecision($abstract->fresh(['user', 'subtheme', 'reviewer'])));

        $message = match ($data['action']) {
            'accepted' => 'Abstract accepted and the author has been notified.',
            'revision_requested' => 'Revision requested and the author has been notified.',
            'rejected' => 'Abstract permanently rejected and the author has been notified.',
        };

        return to_route('admin.abstracts.show', $abstract)->with('success', $message);
    }
}
