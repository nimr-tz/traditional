<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\StudentVerificationSubmitted;
use App\Models\User;
use App\Support\FeeTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    /**
     * Let a registrant fix their participant type / registration category
     * before they've requested a control number — e.g. they picked the wrong
     * region or student status by mistake at registration. Once a control
     * number has been requested, the fee is already tied to a billing
     * request, so changing category here is blocked.
     *
     * The same tier rules as registration apply (App\Support\FeeTier): this
     * endpoint sets the price, so without them it would be a second, quieter
     * route to the cheaper regional rate.
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

        FeeTier::guard($data['fee_category'], $data['participant_type'], $user->country);

        // Switching *into* a student rate needs proof, exactly as registering
        // into one does — otherwise this route mints student-priced accounts
        // with a verification queue entry and no document to review. Checked
        // after the tier guard so a wrong category is reported as a wrong
        // category, not as a missing document.
        $needsDocument = FeeTier::isStudentCategory($data['fee_category']) && ! $user->student_document_path;

        $request->validate([
            'student_document' => [
                Rule::requiredIf($needsDocument),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
        ], [
            'student_document.required' => 'Upload your student verification document to switch to a student registration category.',
        ]);

        $wasStudent = $user->requiresStudentVerification();

        $user->participant_type = $data['participant_type'];
        $user->assignFeeCategory($data['fee_category']);

        $newDocumentPath = $request->hasFile('student_document')
            ? $request->file('student_document')->store('student-verification', 'local')
            : null;
        $oldDocumentPath = $newDocumentPath ? $user->student_document_path : null;

        if ($newDocumentPath) {
            $user->student_document_path = $newDocumentPath;
            $user->student_verified_at = null;
            $user->student_verified_by = null;
            $user->student_verification_notes = null;
        }

        if (! $user->requiresStudentVerification()) {
            $user->student_verification_status = null;
        } elseif (! $wasStudent || $newDocumentPath) {
            $user->student_verification_status = 'pending';
        }

        try {
            $user->save();
        } catch (\Throwable $exception) {
            if ($newDocumentPath) {
                Storage::disk('local')->delete($newDocumentPath);
            }

            throw $exception;
        }

        if ($oldDocumentPath) {
            Storage::disk('local')->delete($oldDocumentPath);
        }

        if ($newDocumentPath) {
            $isReplacement = (bool) $oldDocumentPath;

            User::query()
                ->withRole(User::ADMIN_ROLES)
                ->pluck('email')
                ->each(fn (string $email) => Mail::to($email)->send(new StudentVerificationSubmitted($user, $isReplacement)));
        }

        return back()->with('success', 'Your registration category has been updated.');
    }
}
