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

        if ($user->payment_status === 'submitted') {
            return back()->with('info', 'A control number has already been requested. Please wait for it to appear.');
        }

        if (! $user->hasVerifiedStudentStatus()) {
            return back()->with('error', 'Your student status must be verified before a control number can be issued.');
        }

        $this->gepg->requestControlNumber($user);

        return back()->with('success', 'Your control number has been requested. It will appear here shortly.');
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
}
