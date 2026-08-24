<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistrationEmailChange;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::withRole(User::ROLE_USER)
            ->when($request->payment_status, fn ($q, $status) => $q->where('payment_status', $status))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            }));

        return Inertia::render('admin/registrations/index', [
            'registrations' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['payment_status', 'search']),
            'counts' => [
                'total' => User::withRole(User::ROLE_USER)->count(),
                'pending' => User::withRole(User::ROLE_USER)->where('payment_status', 'pending')->count(),
                'submitted' => User::withRole(User::ROLE_USER)->where('payment_status', 'submitted')->count(),
                'verified' => User::withRole(User::ROLE_USER)->where('payment_status', 'verified')->count(),
            ],
            'emailChanges' => RegistrationEmailChange::query()
                ->latest()
                ->limit(10)
                ->get(['id', 'previous_email', 'new_email', 'changed_by_name', 'reason', 'created_at']),
        ]);
    }

    /**
     * Correct a registrant's email address.
     *
     * Registrants can't change their own email (it's their identity for the
     * badge, certificate, and control number — see ProfileUpdateRequest), which
     * leaves a typo at registration otherwise unrecoverable: the verification
     * link goes to an address they don't own, so they can never reach the
     * dashboard, never get a control number, and the mistyped address stays
     * permanently claimed by the dead account.
     *
     * Changing it necessarily un-verifies the account — the new address hasn't
     * proven ownership — so this re-sends verification to the corrected
     * address, and records who did it.
     */
    public function updateEmail(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 403, 'Only registrant accounts can be corrected here.');

        $data = $request->validate([
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['email'] === $user->email) {
            return back()->with('error', 'That is already this registrant\'s email address.');
        }

        $previousEmail = $user->email;

        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();

        RegistrationEmailChange::create([
            'user_id' => $user->id,
            'previous_email' => $previousEmail,
            'new_email' => $user->email,
            'changed_by' => $request->user()->id,
            'changed_by_name' => $request->user()->full_name,
            'changed_by_email' => $request->user()->email,
            'reason' => $data['reason'] ?? null,
        ]);

        $user->sendEmailVerificationNotification();

        return back()->with(
            'success',
            "Email updated to {$user->email}. A new verification link has been sent to that address."
        );
    }

    public function export(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Name', 'Email', 'Institution', 'Phone', 'Country', 'Participant Type', 'Fee Category', 'Amount', 'Currency', 'Payment Status', 'Paid At', 'Checked In'];
        $sheet->fromArray($headers, null, 'A1');

        $rows = User::withRole(User::ROLE_USER)->with('attendance')->get()->map(fn (User $user) => [
            $user->full_name,
            $user->email,
            $user->institution,
            $user->phone,
            $user->country,
            $user->participant_type,
            $user->fee_category,
            $user->fee_amount,
            $user->currency,
            $user->payment_status,
            $user->paid_at?->format('Y-m-d H:i'),
            $user->isCheckedIn() ? 'Yes' : 'No',
        ])->toArray();

        $sheet->fromArray($rows, null, 'A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'tmsc-registrations-'.now()->format('Y-m-d').'.xlsx');
    }
}
