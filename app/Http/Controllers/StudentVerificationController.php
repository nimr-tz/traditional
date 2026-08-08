<?php

namespace App\Http\Controllers;

use App\Mail\StudentVerificationSubmitted;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class StudentVerificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Either they are on a student rate awaiting proof, or they were refused
        // one and want another look. The second case is deliberately allowed
        // while they sit on a participant rate: applying again must not disturb
        // the rate they are currently free to pay. Only approval moves it.
        abort_unless(
            $user->requiresStudentVerification() || $user->hasStudentRateApplication(),
            403,
        );

        $request->validate([
            'student_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $newPath = $request->file('student_document')->store('student-verification', 'local');
        $oldPath = $user->student_document_path;

        $user->forceFill([
            'student_document_path' => $newPath,
            'student_verification_status' => 'pending',
            'student_verified_at' => null,
            'student_verified_by' => null,
            'student_verification_notes' => null,
        ])->save();

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        User::query()
            ->withRole(User::ADMIN_ROLES)
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->send(new StudentVerificationSubmitted($user, true)));

        return back()->with('success', 'Your student document has been submitted for verification.');
    }
}
