<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Mail\PaymentRejected;
use App\Models\FeeCategory;
use App\Models\User;
use App\Services\Billing\GepgService;
use App\Services\Sms\SmsNotifier;
use App\Support\ConferenceEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function dashboard(): Response
    {
        $registrants = User::withRole(User::ROLE_USER);

        $stats = [
            'verified_count' => (clone $registrants)->where('payment_status', 'verified')->count(),
            'submitted_count' => (clone $registrants)->where('payment_status', 'submitted')->count(),
            'waived_count' => (clone $registrants)->where('payment_status', 'waived')->count(),
            'rejected_count' => (clone $registrants)->where('payment_status', 'rejected')->count(),
            'not_started_count' => (clone $registrants)->where('payment_status', 'pending')->count(),
        ];

        $currencies = FeeCategory::query()->distinct()->pluck('currency');
        $revenue = [];
        foreach ($currencies as $currency) {
            $revenue[$currency] = ['realized' => 0, 'outstanding' => 0, 'waived' => 0, 'projected' => 0];
        }

        (clone $registrants)->whereNotNull('fee_category')->get(['currency', 'fee_amount', 'payment_status'])
            ->each(function (User $user) use (&$revenue) {
                $currency = $user->currency;
                $amount = (float) $user->fee_amount;
                $revenue[$currency] ??= ['realized' => 0, 'outstanding' => 0, 'waived' => 0, 'projected' => 0];

                if ($user->payment_status === 'verified') {
                    $revenue[$currency]['realized'] += $amount;
                } elseif ($user->payment_status === 'waived') {
                    $revenue[$currency]['waived'] += $amount;
                } elseif ($user->payment_status !== 'rejected') {
                    $revenue[$currency]['outstanding'] += $amount;
                }

                if (! in_array($user->payment_status, ['rejected', 'waived'], true)) {
                    $revenue[$currency]['projected'] += $amount;
                }
            });

        $todayUsers = (clone $registrants)->where('payment_status', 'verified')->whereDate('paid_at', today())->get(['currency', 'fee_amount']);

        $todayRevenue = [];
        foreach ($todayUsers as $user) {
            $todayRevenue[$user->currency] = ($todayRevenue[$user->currency] ?? 0) + (float) $user->fee_amount;
        }

        return Inertia::render('admin/finance/dashboard', [
            'stats' => $stats,
            'revenue' => $revenue,
            'todayVerifiedCount' => $todayUsers->count(),
            'todayRevenue' => $todayRevenue,
            'pendingPayments' => (clone $registrants)->where('payment_status', 'submitted')
                ->latest('updated_at')->limit(5)->get(['id', 'name', 'salutation', 'email', 'fee_category', 'updated_at']),
            'categoryStats' => (clone $registrants)->whereNotNull('fee_category')
                ->select('fee_category', DB::raw('count(*) as total'))
                ->groupBy('fee_category')
                ->pluck('total', 'fee_category'),
        ]);
    }

    public function payments(Request $request): Response
    {
        $query = User::withRole(User::ROLE_USER)
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('payment_status', $request->status))
            ->when($request->method, fn ($q) => $q->where('payment_method', $request->method))
            ->when($request->category && $request->category !== 'all', fn ($q) => $q->where('fee_category', $request->category))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('control_number', 'like', "%{$search}%");
            }));

        $registrants = User::withRole(User::ROLE_USER);

        return Inertia::render('admin/finance/payments', [
            'registrations' => $query->latest('updated_at')->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'search', 'category', 'method']),
            'stats' => [
                'verified_count' => (clone $registrants)->where('payment_status', 'verified')->count(),
                'submitted_count' => (clone $registrants)->where('payment_status', 'submitted')->count(),
                'waived_count' => (clone $registrants)->where('payment_status', 'waived')->count(),
                'not_started_count' => (clone $registrants)->where('payment_status', 'pending')->count(),
            ],
        ]);
    }

    public function show(User $user): Response
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);

        return Inertia::render('admin/finance/show', [
            'registration' => $user->only([
                'id', 'name', 'full_name', 'email', 'fee_category', 'fee_amount', 'currency',
                'control_number', 'billing_request_id', 'payment_method', 'payment_proof_path',
                'payment_status', 'payment_notes', 'paid_at', 'created_at', 'updated_at',
            ]),
        ]);
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);
        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $user->forceFill([
            'payment_status' => 'verified',
            'paid_at' => now(),
            'payment_verified_by' => Auth::id(),
            'payment_notes' => $request->notes ?: 'Verified by finance.',
        ]);

        if (! $user->registration_code) {
            $user->generateRegistrationCode();
        }

        $user->save();

        ConferenceEmail::sendTo($user, new PaymentConfirmed($user));
        app(SmsNotifier::class)->paymentConfirmed($user);

        return back()->with('success', "Payment for {$user->full_name} has been verified.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        if ($user->payment_method === 'bank_transfer') {
            $user->forceFill([
                'payment_status' => 'pending',
                'payment_proof_path' => null,
                'payment_notes' => 'Proof rejected: '.$request->notes,
            ])->save();
        } else {
            $user->forceFill([
                'payment_status' => 'rejected',
                'payment_notes' => $request->notes,
            ])->save();
        }

        ConferenceEmail::sendTo($user, new PaymentRejected($user, $request->notes));

        return back()->with('success', "Payment for {$user->full_name} has been rejected.");
    }

    public function waive(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(User::ROLE_USER), 404);
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $user->forceFill([
            'payment_status' => 'waived',
            'paid_at' => now(),
            'payment_verified_by' => Auth::id(),
            'payment_notes' => $request->notes,
        ]);

        if (! $user->registration_code) {
            $user->generateRegistrationCode();
        }

        $user->save();

        ConferenceEmail::sendTo($user, new FeeWaived($user, $request->notes));

        return back()->with('success', "Registration fee for {$user->full_name} has been waived.");
    }

    public function returnWaiverToPayment(User $user): RedirectResponse
    {
        abort_unless($user->payment_status === 'waived', 422, 'Only waived payments can be returned to payment.');

        $hasPaymentProgress = $user->billing_request_id || $user->control_number || $user->payment_proof_path;

        $user->forceFill([
            'payment_status' => $hasPaymentProgress ? 'submitted' : 'pending',
            'paid_at' => null,
            'payment_verified_by' => null,
            'payment_notes' => 'Waiver removed by finance. Participant returned to the payment flow.',
        ])->save();

        return back()->with('success', "{$user->full_name} has been returned to the payment flow.");
    }

    public function resetBilling(User $user): RedirectResponse
    {
        abort_unless(
            in_array($user->payment_status, ['submitted', 'pending', 'rejected'], true),
            422,
            "Cannot reset billing for a {$user->payment_status} payment.",
        );

        $oldBillId = $user->billing_request_id;

        $user->forceFill([
            'payment_status' => 'pending',
            'billing_request_id' => null,
            'control_number' => null,
            'payment_notes' => "Billing reset by finance (old bill ID: {$oldBillId}). Participant may re-request a control number.",
        ])->save();

        return back()->with('success', "Billing request reset for {$user->full_name}.");
    }

    public function downloadInvoice(User $user, GepgService $gepg)
    {
        return $this->downloadBillingDocument($user, $gepg, 'invoice');
    }

    public function downloadReceipt(User $user, GepgService $gepg)
    {
        return $this->downloadBillingDocument($user, $gepg, 'receipt');
    }

    private function downloadBillingDocument(User $user, GepgService $gepg, string $documentType): RedirectResponse|\Illuminate\Http\Response
    {
        abort_unless($user->role === User::ROLE_USER, 404);

        $document = $gepg->fetchBillDocument($user, $documentType);

        if (! $document['success']) {
            return back()->with('error', $document['message'] ?? "Unable to download {$documentType}.");
        }

        return response($document['body'])
            ->header('Content-Type', $document['content_type'])
            ->header('Content-Disposition', 'attachment; filename="'.$document['filename'].'"');
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'individuals');

        if ($type === 'summary') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="tmsc_finance_summary_'.now()->format('Y-m-d').'.csv"',
            ];

            return response()->stream(function () {
                $registrants = User::withRole(User::ROLE_USER);

                $file = fopen('php://output', 'w');
                fputcsv($file, ['Metric', 'Value']);
                fputcsv($file, ['Verified', (clone $registrants)->where('payment_status', 'verified')->count()]);
                fputcsv($file, ['Submitted', (clone $registrants)->where('payment_status', 'submitted')->count()]);
                fputcsv($file, ['Waived', (clone $registrants)->where('payment_status', 'waived')->count()]);
                fputcsv($file, ['Rejected', (clone $registrants)->where('payment_status', 'rejected')->count()]);
                fputcsv($file, ['Not started', (clone $registrants)->where('payment_status', 'pending')->count()]);
                fputcsv($file, []);
                fputcsv($file, ['Fee category', 'Count']);

                (clone $registrants)->whereNotNull('fee_category')
                    ->select('fee_category', DB::raw('count(*) as total'))
                    ->groupBy('fee_category')
                    ->orderBy('fee_category')
                    ->each(fn ($row) => fputcsv($file, [$row->fee_category, $row->total]));

                fclose($file);
            }, 200, $headers);
        }

        $query = User::withRole(User::ROLE_USER)
            ->when($status !== 'all', fn ($q) => $q->where('payment_status', $status))
            ->with('verifiedBy:id,name,salutation');

        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Email', 'Category', 'Currency', 'Amount', 'Control Number', 'Status', 'Verified At', 'Verified By', 'Notes']);

            $query->orderBy('updated_at', 'desc')->each(function (User $user) use ($file) {
                fputcsv($file, [
                    $user->full_name,
                    $user->email,
                    $user->fee_category ?? 'Not set',
                    $user->currency,
                    $user->fee_amount,
                    $user->control_number ?? '-',
                    $user->payment_status,
                    $user->paid_at?->format('Y-m-d H:i') ?? '-',
                    $user->verifiedBy?->full_name ?? '-',
                    $user->payment_notes ?? '',
                ]);
            });

            fclose($file);
        }, 'tmsc_finance_individuals_'.now()->format('Y-m-d').'.csv');
    }
}
