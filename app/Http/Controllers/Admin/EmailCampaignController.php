<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaign;
use App\Mail\CampaignMessage;
use App\Models\EmailCampaign;
use App\Models\User;
use App\Support\RegistrantAudience;
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
            'segments' => RegistrantAudience::SEGMENTS,
            'parameterisedSegments' => RegistrantAudience::PARAMETERISED,
            'audienceOptions' => RegistrantAudience::options(),
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
            'count' => RegistrantAudience::count($data['audience'], $data['audience_value'] ?? null),
            'label' => RegistrantAudience::label($data['audience'], $data['audience_value'] ?? null),
        ]);
    }

    /**
     * Render the composed message exactly as a recipient would receive it,
     * without sending anything. Lets an admin check formatting/paragraph
     * breaks before a send that can't be recalled.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $campaign = new EmailCampaign($data);

        return response()->json([
            'html' => (new CampaignMessage($campaign, $request->user()->full_name))->render(),
        ]);
    }

    /**
     * Send the composed message to a single address — the acting admin's own
     * by default, or any address they type in, so a real inbox (their own or
     * someone else's, with permission) can be checked before the real send.
     * Campaign emails go to real participants and can't be recalled, so there
     * is always a way to see the rendered result first without involving
     * anyone else.
     */
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $admin = $request->user();
        $to = ($data['email'] ?? null) ?: $admin->email;

        // Unsaved: a test is a preview, not a campaign, and must not appear in
        // the sent history or count against anybody's record.
        $campaign = new EmailCampaign([
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        Mail::to($to)->send(new CampaignMessage($campaign, $admin->full_name));

        return back()->with('success', "Test email sent to {$to}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAudience($request, [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $audience = $data['audience'];
        $value = $data['audience_value'] ?? null;

        $recipients = RegistrantAudience::query($audience, $value)->get(['id', 'name', 'salutation', 'email']);

        if ($recipients->isEmpty()) {
            return back()->with('error', 'That audience currently has no recipients, so nothing was sent.');
        }

        $campaign = DB::transaction(function () use ($request, $data, $audience, $value, $recipients) {
            $campaign = EmailCampaign::create([
                'subject' => $data['subject'],
                'body' => $data['body'],
                'audience' => $audience,
                'audience_label' => RegistrantAudience::label($audience, $value),
                'audience_value' => $value,
                'recipient_count' => $recipients->count(),
                'status' => 'queued',
                'created_by' => $request->user()->id,
                'created_by_name' => $request->user()->full_name,
                'created_by_email' => $request->user()->email,
            ]);

            // Materialised now, not at delivery time — see SendEmailCampaign.
            $campaign->recipients()->createMany(
                $recipients->map(fn (User $user) => [
                    'user_id' => $user->id,
                    'name' => $user->full_name,
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
            'audience' => ['required', Rule::in(array_keys(RegistrantAudience::SEGMENTS))],
            'audience_value' => [
                Rule::requiredIf(fn () => RegistrantAudience::needsValue((string) $request->input('audience'))),
                'nullable',
                'string',
                'max:255',
            ],
        ], $extra));
    }
}
