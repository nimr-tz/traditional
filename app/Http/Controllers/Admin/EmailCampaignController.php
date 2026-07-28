<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaign;
use App\Mail\CampaignMessage;
use App\Models\EmailCampaign;
use App\Models\User;
use App\Support\EmailAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailCampaignController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/emails/index', [
            'segments' => EmailAudience::SEGMENTS,
            'parameterisedSegments' => EmailAudience::PARAMETERISED,
            'audienceOptions' => EmailAudience::options(),
            'campaigns' => EmailCampaign::query()
                ->latest()
                ->limit(25)
                ->get([
                    'id', 'subject', 'audience_label', 'recipient_count',
                    'sent_count', 'failed_count', 'status', 'created_by_name', 'created_at',
                ]),
        ]);
    }

    /** Live recipient count for the compose form, so nobody sends blind. */
    public function count(Request $request): JsonResponse
    {
        $data = $this->validateAudience($request);

        return response()->json([
            'count' => EmailAudience::count($data['audience'], $data['audience_value'] ?? null),
            'label' => EmailAudience::label($data['audience'], $data['audience_value'] ?? null),
        ]);
    }

    /**
     * Send the composed message to the acting admin only. Campaign emails go to
     * real participants and can't be recalled, so there is always a way to see
     * the rendered result first without involving anyone else.
     */
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $admin = $request->user();

        // Unsaved: a test is a preview, not a campaign, and must not appear in
        // the sent history or count against anybody's record.
        $campaign = new EmailCampaign([
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        Mail::to($admin->email)->send(new CampaignMessage($campaign, $admin->name));

        return back()->with('success', "Test email sent to {$admin->email}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAudience($request, [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $audience = $data['audience'];
        $value = $data['audience_value'] ?? null;

        $recipients = EmailAudience::query($audience, $value)->get(['id', 'name', 'email']);

        if ($recipients->isEmpty()) {
            return back()->with('error', 'That audience currently has no recipients, so nothing was sent.');
        }

        $campaign = DB::transaction(function () use ($request, $data, $audience, $value, $recipients) {
            $campaign = EmailCampaign::create([
                'subject' => $data['subject'],
                'body' => $data['body'],
                'audience' => $audience,
                'audience_label' => EmailAudience::label($audience, $value),
                'audience_value' => $value,
                'recipient_count' => $recipients->count(),
                'status' => 'queued',
                'created_by' => $request->user()->id,
                'created_by_name' => $request->user()->name,
                'created_by_email' => $request->user()->email,
            ]);

            // Materialised now, not at delivery time — see SendEmailCampaign.
            $campaign->recipients()->createMany(
                $recipients->map(fn (User $user) => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])->all()
            );

            return $campaign;
        });

        SendEmailCampaign::dispatch($campaign->id);

        return back()->with(
            'success',
            "Queued for {$campaign->recipient_count} recipient(s). Delivery happens in the background."
        );
    }

    public function show(EmailCampaign $campaign): Response
    {
        return Inertia::render('admin/emails/show', [
            'campaign' => $campaign->only([
                'id', 'subject', 'body', 'audience_label', 'recipient_count',
                'sent_count', 'failed_count', 'status', 'created_by_name', 'created_at', 'completed_at',
            ]),
            'recipients' => $campaign->recipients()
                ->orderByRaw("case when status = 'failed' then 0 else 1 end")
                ->orderBy('name')
                ->paginate(50)
                ->through(fn ($r) => $r->only(['id', 'name', 'email', 'status', 'error', 'sent_at'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validateAudience(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'audience' => ['required', Rule::in(array_keys(EmailAudience::SEGMENTS))],
            'audience_value' => [
                Rule::requiredIf(fn () => EmailAudience::needsValue((string) $request->input('audience'))),
                'nullable',
                'string',
                'max:255',
            ],
        ], $extra));
    }
}
