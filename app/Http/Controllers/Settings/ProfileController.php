<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\FeeCategory;
use App\Support\FeeTier;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'participantTypes' => config('tmsc.participant_types'),
            'feeCategories' => FeeCategory::query()->where('active', true)->orderBy('sort_order')->get(['key', 'label', 'amount', 'currency']),
            'registration' => [
                'participant_type' => $user->participant_type,
                'fee_category' => $user->fee_category,
                'country' => $user->country,
                // The tier the user's country entitles them to. The form filters
                // the category list by this so the server-side check in
                // App\Support\FeeTier is never the first thing they hear about it.
                'region' => FeeTier::regionOfCountry($user->country),
                'payment_status' => $user->payment_status,
                'requires_student_verification' => $user->requiresStudentVerification(),
                'student_verification_status' => $user->student_verification_status,
                'student_verification_notes' => $user->student_verification_notes,
                'has_student_document' => (bool) $user->student_document_path,
            ],
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return to_route('profile.edit');
    }
}
