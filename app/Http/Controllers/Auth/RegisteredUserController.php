<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationConfirmed;
use App\Mail\StudentVerificationSubmitted;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'salutations' => config('tmsc.salutations'),
            'participantTypes' => config('tmsc.participant_types'),
            'feeCategories' => FeeCategory::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(['key', 'label', 'amount', 'currency']),
            'eastAfricaCountries' => config('tmsc.east_africa_countries'),
            'institutions' => Institution::where('active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'salutation' => ['required', Rule::in(config('tmsc.salutations'))],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'institution_id' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    if ($value === 'other') {
                        return;
                    }
                    if (! Institution::where('id', $value)->where('active', true)->exists()) {
                        $fail('Please select a valid institution.');
                    }
                },
            ],
            'institution_other' => ['required_if:institution_id,other', 'nullable', 'string', 'max:255'],
            'phone' => 'required|string|max:50',
            'participant_type' => ['required', Rule::in(array_keys(config('tmsc.participant_types')))],
            'country' => 'required|string|max:100',
            'fee_category' => [
                'required',
                Rule::exists('fee_categories', 'key')->where(fn ($query) => $query->where('active', true)),
            ],
            'student_document' => [
                Rule::requiredIf(fn () => $request->participant_type === 'student'),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $isEastAfrica = in_array($request->country, config('tmsc.east_africa_countries'), true);
        $isStudentCategory = str_starts_with((string) $request->fee_category, 'student_');

        if (($request->participant_type === 'student') !== $isStudentCategory) {
            throw ValidationException::withMessages([
                'fee_category' => 'Please select the registration category that matches your participant type.',
            ]);
        }

        if ($request->institution_id === 'other') {
            $institutionId = null;
            $institutionName = $request->institution_other;
        } else {
            $institution = Institution::findOrFail($request->institution_id);
            $institutionId = $institution->id;
            $institutionName = $institution->name;
        }

        $studentDocumentPath = $isStudentCategory
            ? $request->file('student_document')->store('student-verification', 'local')
            : null;

        $user = new User([
            'salutation' => $request->salutation,
            'name' => implode(' ', array_filter([
                $request->first_name,
                $request->middle_name,
                $request->last_name,
            ])),
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'institution' => $institutionName,
            'institution_id' => $institutionId,
            'phone' => $request->phone,
            'country' => $request->country,
            'is_east_africa' => $isEastAfrica,
            'participant_type' => $request->participant_type,
            'student_document_path' => $studentDocumentPath,
            'student_verification_status' => $isStudentCategory ? 'pending' : null,
            'password' => Hash::make($request->password),
        ]);

        $user->assignFeeCategory($request->fee_category);
        try {
            $user->save();
        } catch (\Throwable $exception) {
            if ($studentDocumentPath) {
                Storage::disk('local')->delete($studentDocumentPath);
            }

            throw $exception;
        }

        event(new Registered($user));

        Mail::to($user->email)->send(new RegistrationConfirmed($user));

        if ($user->requiresStudentVerification()) {
            User::query()
                ->withRole(User::ADMIN_ROLES)
                ->pluck('email')
                ->each(fn (string $email) => Mail::to($email)->send(new StudentVerificationSubmitted($user)));
        }

        Auth::login($user);

        return to_route('verification.notice')->with('status', 'verification-link-sent');
    }
}
