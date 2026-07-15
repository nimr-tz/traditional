<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentVerificationApproved;
use App\Mail\StudentVerificationRejected;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentVerificationController extends Controller
{
    private const STUDENT_FEE_CATEGORIES = [
        'student_east_africa',
        'student_non_east_africa',
    ];

    public function index(Request $request): Response
    {
        $students = User::query()
            ->where('is_admin', false)
            ->whereIn('fee_category', self::STUDENT_FEE_CATEGORIES)
            ->when($request->status && $request->status !== 'all', fn ($query) => $query
                ->where('student_verification_status', $request->status))
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $studentQuery = User::query()
            ->where('is_admin', false)
            ->whereIn('fee_category', self::STUDENT_FEE_CATEGORIES);

        return Inertia::render('admin/students/index', [
            'students' => $students,
            'filters' => $request->only(['status', 'search']),
            'stats' => [
                'pending' => (clone $studentQuery)->where('student_verification_status', 'pending')->count(),
                'verified' => (clone $studentQuery)->where('student_verification_status', 'verified')->count(),
                'rejected' => (clone $studentQuery)->where('student_verification_status', 'rejected')->count(),
                'total' => (clone $studentQuery)->count(),
            ],
        ]);
    }

    public function document(User $user): StreamedResponse
    {
        abort_unless($user->requiresStudentVerification() && $user->student_document_path, 404);

        $extension = pathinfo($user->student_document_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $user->student_document_path,
            'student-document-'.$user->id.'.'.$extension,
        );
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $user->requiresStudentVerification()
                && $user->student_document_path
                && $user->student_verification_status === 'pending',
            422,
        );

        $user->forceFill([
            'student_verification_status' => 'verified',
            'student_verified_at' => now(),
            'student_verified_by' => Auth::id(),
            'student_verification_notes' => $request->notes,
        ])->save();

        Mail::to($user->email)->send(new StudentVerificationApproved($user));

        return back()->with('success', "Student status for {$user->name} has been verified.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        abort_unless(
            $user->requiresStudentVerification()
                && $user->student_document_path
                && $user->student_verification_status === 'pending',
            422,
        );

        $user->forceFill([
            'student_verification_status' => 'rejected',
            'student_verified_at' => now(),
            'student_verified_by' => Auth::id(),
            'student_verification_notes' => $request->notes,
        ])->save();

        Mail::to($user->email)->send(new StudentVerificationRejected($user));

        return back()->with('success', "Student document for {$user->name} has been rejected.");
    }
}
