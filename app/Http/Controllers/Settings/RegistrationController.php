<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    /**
     * Let a registrant fix their participant type / registration category
     * before they've requested a control number — e.g. they picked the wrong
     * region or student status by mistake at registration. Once a control
     * number has been requested, the fee is already tied to a billing
     * request, so changing category here is blocked.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->payment_status !== 'pending') {
            return back()->with(
                'error',
                'Your registration category can no longer be changed once a control number has been requested. Please contact the organizers.'
            );
        }

        $data = $request->validate([
            'participant_type' => ['required', Rule::in(array_keys(config('tmsc.participant_types')))],
            'fee_category' => [
                'required',
                Rule::exists('fee_categories', 'key')->where(fn ($query) => $query->where('active', true)),
            ],
        ]);

        $isStudentCategory = str_starts_with($data['fee_category'], 'student_');

        if (($data['participant_type'] === 'student') !== $isStudentCategory) {
            throw ValidationException::withMessages([
                'fee_category' => 'Please select the registration category that matches your participant type.',
            ]);
        }

        $wasStudent = $user->requiresStudentVerification();

        $user->participant_type = $data['participant_type'];
        $user->assignFeeCategory($data['fee_category']);

        if (! $user->requiresStudentVerification()) {
            $user->student_verification_status = null;
        } elseif (! $wasStudent) {
            $user->student_verification_status = 'pending';
        }

        $user->save();

        return back()->with('success', 'Your registration category has been updated.');
    }
}
