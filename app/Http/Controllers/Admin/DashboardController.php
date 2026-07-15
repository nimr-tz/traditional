<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $registrants = User::where('is_admin', false);
        $students = User::query()
            ->where('is_admin', false)
            ->whereIn('fee_category', ['student_east_africa', 'student_non_east_africa']);

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_registrants' => (clone $registrants)->count(),
                'paid' => (clone $registrants)->where('payment_status', 'verified')->count(),
                'pending_payment' => (clone $registrants)->where('payment_status', 'submitted')->count(),
                'checked_in' => (clone $registrants)->whereHas('attendance')->count(),
                'abstracts_total' => AbstractSubmission::count(),
                'abstracts_submitted' => AbstractSubmission::where('status', 'submitted')->count(),
                'abstracts_revision_requested' => AbstractSubmission::where('status', 'revision_requested')->count(),
                'abstracts_accepted' => AbstractSubmission::where('status', 'accepted')->count(),
                'abstracts_rejected' => AbstractSubmission::where('status', 'rejected')->count(),
                'students_pending' => (clone $students)->where('student_verification_status', 'pending')->count(),
            ],
            'reviewQueue' => AbstractSubmission::query()
                ->with(['user:id,name,email,institution', 'subtheme:id,title'])
                ->where('status', 'submitted')
                ->oldest('resubmitted_at')
                ->oldest('created_at')
                ->limit(6)
                ->get(),
            'studentQueue' => (clone $students)
                ->where('student_verification_status', 'pending')
                ->oldest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'institution', 'fee_category', 'created_at']),
        ]);
    }
}
