<?php

namespace App\Http\Controllers;

use App\Models\FeeCategory;
use App\Services\Billing\GepgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private GepgService $gepg) {}

    public function show(): Response
    {
        $user = Auth::user();

        return Inertia::render('payment', [
            'user' => $user->only([
                'fee_amount', 'currency', 'control_number',
                'payment_status', 'paid_at', 'registration_code',
                'student_verification_status', 'student_verification_notes',
            ]),
            'registrationCategory' => FeeCategory::query()
                ->where('key', $user->fee_category)
                ->value('label'),
            'requiresStudentVerification' => $user->requiresStudentVerification(),
            'gepgPayeeName' => config('billing.payee_name'),
            'canReplaceSandboxControlNumber' => ! config('billing.sandbox')
                && $user->payment_status !== 'verified'
                && $user->hasSandboxBillingRequest(),
        ]);
    }

    public function requestControlNumber(): RedirectResponse
    {
        $user = Auth::user();

        if (! config('billing.enabled')) {
            return back()->with('error', 'The payment portal is currently closed. Please try again shortly.');
        }

        if ($user->payment_status === 'verified') {
            return back()->with('info', 'Your payment is already verified.');
        }

        $canReplaceSandboxControlNumber = ! config('billing.sandbox')
            && $user->hasSandboxBillingRequest();

        if (! $user->hasVerifiedStudentStatus()) {
            return back()->with('error', 'Your student status must be verified before a control number can be issued.');
        }

        $previousBillId = $user->billing_request_id;
        $this->gepg->requestControlNumber($user);
        $user->refresh();

        if ($previousBillId && $previousBillId === $user->billing_request_id) {
            return back()->with('info', 'Your control number request is still being processed.');
        }

        $message = $canReplaceSandboxControlNumber
            ? 'Your valid control number has been requested. It will appear here shortly.'
            : 'Your control number has been requested. It will appear here shortly.';

        return back()->with('success', $message);
    }

    public function pollStatus(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'control_number' => $user->control_number,
            'payment_status' => $user->payment_status,
            'registration_code' => $user->registration_code,
        ]);
    }

    public function downloadInvoice(): RedirectResponse|\Illuminate\Http\Response
    {
        return $this->downloadBillingDocument('invoice');
    }

    public function downloadReceipt(): RedirectResponse|\Illuminate\Http\Response
    {
        return $this->downloadBillingDocument('receipt');
    }

    private function downloadBillingDocument(string $documentType): RedirectResponse|\Illuminate\Http\Response
    {
        $document = $this->gepg->fetchBillDocument(Auth::user(), $documentType);

        if (! $document['success']) {
            return back()->with('error', $document['message'] ?? "Unable to download {$documentType}.");
        }

        return response($document['body'])
            ->header('Content-Type', $document['content_type'])
            ->header('Content-Disposition', 'attachment; filename="'.$document['filename'].'"');
    }
}
