<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsCampaign;
use App\Models\SmsCampaign;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Support\RegistrantAudience;
use App\Support\TanzanianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SmsCampaignController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/sms/index', [
            'segments' => RegistrantAudience::SEGMENTS,
            'parameterisedSegments' => RegistrantAudience::PARAMETERISED,
            'audienceOptions' => RegistrantAudience::options(),
            'campaigns' => SmsCampaign::query()
                ->latest()
                ->limit(25)
                ->get([
                    'id', 'message', 'audience_label', 'recipient_count',
                    'sent_count', 'failed_count', 'status', 'created_by_name', 'created_at',
                ]),
        ]);
    }

    /** Live reachable-recipient count for the compose form, so nobody sends blind. */
    public function count(Request $request): JsonResponse
    {
        $data = $this->validateAudience($request);

        return response()->json([
            'count' => $this->reachableRecipients($data['audience'], $data['audience_value'] ?? null)->count(),
            'label' => RegistrantAudience::label($data['audience'], $data['audience_value'] ?? null),
        ]);
    }

    /**
     * Send the composed message to the acting admin's own number only.
     * Campaign texts go to real participants and can't be recalled, so there
     * is always a way to see the rendered result first without involving
     * anyone else.
     */
    public function test(Request $request, SmsGateway $gateway): RedirectResponse
    {
        if (! config('sms.enabled')) {
            return back()->with('error', 'SMS sending is currently disabled.');
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:480'],
        ]);

        $admin = $request->user();
        $msisdn = TanzanianPhone::normalize($admin->phone, $admin->country);

        if (! $msisdn) {
            return back()->with('error', 'Your account doesn\'t have a usable Tanzanian mobile number on file.');
        }

        try {
            $gateway->send($msisdn, $data['message'], ['source' => 'sms-campaign-test', 'user_id' => $admin->id]);
        } catch (Throwable $exception) {
            return back()->with('error', 'The SMS gateway rejected the test message: '.$exception->getMessage());
        }

        return back()->with('success', "Test SMS sent to {$msisdn}.");
    }

    public function store(Request $request): RedirectResponse
    {
        if (! config('sms.enabled')) {
            return back()->with('error', 'SMS sending is currently disabled.');
        }

        $data = $this->validateAudience($request, [
            'message' => ['required', 'string', 'max:480'],
        ]);

        $audience = $data['audience'];
        $value = $data['audience_value'] ?? null;

        $recipients = $this->reachableRecipients($audience, $value);

        if ($recipients->isEmpty()) {
            return back()->with('error', 'That audience currently has no recipients with a usable Tanzanian mobile number, so nothing was sent.');
        }

        $campaign = DB::transaction(function () use ($request, $data, $audience, $value, $recipients) {
            $campaign = SmsCampaign::create([
                'message' => $data['message'],
                'audience' => $audience,
                'audience_label' => RegistrantAudience::label($audience, $value),
                'audience_value' => $value,
                'recipient_count' => $recipients->count(),
                'status' => 'queued',
                'created_by' => $request->user()->id,
                'created_by_name' => $request->user()->name,
                'created_by_email' => $request->user()->email,
            ]);

            // Materialised now, not at delivery time — see SendSmsCampaign.
            $campaign->recipients()->createMany(
                $recipients->map(fn (array $recipient) => [
                    'user_id' => $recipient['user_id'],
                    'name' => $recipient['name'],
                    'phone' => $recipient['phone'],
                ])->all()
            );

            return $campaign;
        });

        SendSmsCampaign::dispatch($campaign->id);

        return back()->with(
            'success',
            "Queued for {$campaign->recipient_count} recipient(s). Delivery happens in the background."
        );
    }

    public function show(SmsCampaign $campaign): Response
    {
        return Inertia::render('admin/sms/show', [
            'campaign' => $campaign->only([
                'id', 'message', 'audience_label', 'recipient_count',
                'sent_count', 'failed_count', 'status', 'created_by_name', 'created_at', 'completed_at',
            ]),
            'recipients' => $campaign->recipients()
                ->orderByRaw("case when status = 'failed' then 0 else 1 end")
                ->orderBy('name')
                ->paginate(50)
                ->through(fn ($r) => $r->only(['id', 'name', 'phone', 'status', 'error', 'sent_at'])),
        ]);
    }

    /**
     * Members of the segment who can actually be reached by SMS. Filtered in
     * PHP rather than SQL because reachability depends on combining phone and
     * country per TanzanianPhone's rules, not on either column alone.
     *
     * @return Collection<int, array{user_id: int, name: string, phone: string}>
     */
    private function reachableRecipients(string $audience, ?string $value): Collection
    {
        return RegistrantAudience::query($audience, $value)
            ->get(['id', 'name', 'phone', 'country'])
            ->map(function (User $user) {
                $msisdn = TanzanianPhone::normalize($user->phone, $user->country);

                return $msisdn ? ['user_id' => $user->id, 'name' => $user->name, 'phone' => $msisdn] : null;
            })
            ->filter()
            ->values();
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
