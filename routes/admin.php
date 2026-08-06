<?php

use App\Http\Controllers\Admin\AbstractController as AdminAbstractController;
use App\Http\Controllers\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Admin\BillingMaintenanceController as AdminBillingMaintenanceController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmailCampaignController as AdminEmailCampaignController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\ManagementDashboardController as AdminManagementDashboardController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Admin\ReviewerAssignmentController as AdminReviewerAssignmentController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\StudentVerificationController as AdminStudentVerificationController;
use Illuminate\Support\Facades\Route;

// Abstract browsing/decisions are shared with reviewers, who don't get the rest of the admin panel.
// Reviewers only see abstracts they're assigned to (enforced in the controller).
Route::middleware(['auth', 'role:reviewer,admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('abstracts', [AdminAbstractController::class, 'index'])->name('abstracts.index');
    Route::get('abstracts/{abstract}', [AdminAbstractController::class, 'show'])->name('abstracts.show');
    Route::post('abstracts/{abstract}/reviewer-decision', [AdminAbstractController::class, 'recordReviewerDecision'])->name('abstracts.reviewer-decision');
    // Presentations are not reviewed — organizers can read what was uploaded,
    // but there is no approve/reject step.
    Route::get('abstracts/{abstract}/presentation/download', [AdminAbstractController::class, 'downloadPresentation'])->name('abstracts.presentation.download');
});

// Registrations, student verification, reviewer assignment, and final abstract decisions:
// day-to-day conference operations, open to admin and super_admin alike.
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('abstracts/{abstract}/reviewers', [AdminAbstractController::class, 'assignReviewers'])->name('abstracts.reviewers.assign');
    Route::post('abstracts/{abstract}/decide', [AdminAbstractController::class, 'decide'])->name('abstracts.decide');

    // Pipeline-wide view of who is reviewing what. Assignment writes still go
    // through abstracts.reviewers.assign above — this is the console, not a
    // second implementation of the rules.
    Route::get('assignments', [AdminReviewerAssignmentController::class, 'index'])->name('assignments.index');

    // Reporting view for the organizing committee: totals and breakdowns,
    // as opposed to admin.dashboard which is the day-to-day work queue.
    Route::get('management', [AdminManagementDashboardController::class, 'index'])->name('management.index');

    // Admin-composed announcements to a segment of registrants. Distinct from
    // the transactional mailables, which fire on events and have no author.
    Route::get('emails', [AdminEmailCampaignController::class, 'index'])->name('emails.index');
    Route::get('emails/recipient-count', [AdminEmailCampaignController::class, 'count'])->name('emails.count');
    Route::post('emails/test', [AdminEmailCampaignController::class, 'test'])->name('emails.test');
    Route::post('emails', [AdminEmailCampaignController::class, 'store'])->name('emails.store');
    Route::get('emails/{campaign}', [AdminEmailCampaignController::class, 'show'])->name('emails.show');

    Route::get('registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
    Route::get('registrations/export', [AdminRegistrationController::class, 'export'])->name('registrations.export');
    // The only route that can change a registrant's email — they can't do it
    // themselves, so a typo at registration is otherwise unrecoverable.
    Route::patch('registrations/{user}/email', [AdminRegistrationController::class, 'updateEmail'])->name('registrations.email.update');

    Route::get('students', [AdminStudentVerificationController::class, 'index'])->name('students.index');
    Route::get('students/{user}/document', [AdminStudentVerificationController::class, 'document'])->name('students.document');
    Route::post('students/{user}/verify', [AdminStudentVerificationController::class, 'verify'])->name('students.verify');
    Route::post('students/{user}/reject', [AdminStudentVerificationController::class, 'reject'])->name('students.reject');
    // The undo for a misclicked decision: verify/reject only accept `pending`,
    // so without this a wrong call is permanent.
    Route::post('students/{user}/reopen', [AdminStudentVerificationController::class, 'reopen'])->name('students.reopen');
});

// Conference settings and user/role management are super-admin only — the one console that
// can reshape who has access to everything else, kept deliberately narrower than plain admin.
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings/fee-categories/{feeCategory}', [AdminSettingsController::class, 'updateFeeCategory'])->name('settings.fee-categories.update');
    Route::post('settings/subthemes', [AdminSettingsController::class, 'storeSubtheme'])->name('settings.subthemes.store');
    Route::patch('settings/subthemes/{subtheme}', [AdminSettingsController::class, 'updateSubtheme'])->name('settings.subthemes.update');
    Route::post('settings/institutions', [AdminSettingsController::class, 'storeInstitution'])->name('settings.institutions.store');
    Route::patch('settings/institutions/{institution}', [AdminSettingsController::class, 'updateInstitution'])->name('settings.institutions.update');
    Route::patch('settings/conference', [AdminSettingsController::class, 'updateConferenceSettings'])->name('settings.conference.update');

    // People administration lives on its own, not under conference settings —
    // who can do what has nothing to do with the conference's details.
    Route::get('users', [AdminAdministratorController::class, 'index'])->name('users.index');
    Route::get('users/search', [AdminAdministratorController::class, 'search'])->name('users.search');
    Route::patch('users/{user}/roles', [AdminAdministratorController::class, 'updateRoles'])->name('users.update-roles');

    // One-off cutover maintenance: clears the control numbers and simulated payments sandbox
    // mode left behind. Defaults to a dry run — writing needs an explicit dry_run=false.
    Route::post('billing/purge-sandbox', [AdminBillingMaintenanceController::class, 'purgeSandbox'])
        ->name('billing.purge-sandbox');
});

// Finance manages registrant payment verification, waivers, and reporting — a separate,
// narrower slice of the admin panel that doesn't get abstracts, students, or settings.
Route::middleware(['auth', 'role:finance,admin,super_admin'])->prefix('admin/finance')->name('admin.finance.')->group(function () {
    Route::get('/', [AdminFinanceController::class, 'dashboard'])->name('dashboard');
    Route::get('payments', [AdminFinanceController::class, 'payments'])->name('payments');
    Route::get('payments/{user}', [AdminFinanceController::class, 'show'])->name('payments.show');
    Route::post('payments/{user}/verify', [AdminFinanceController::class, 'verify'])->name('payments.verify');
    Route::post('payments/{user}/reject', [AdminFinanceController::class, 'reject'])->name('payments.reject');
    Route::post('payments/{user}/waive', [AdminFinanceController::class, 'waive'])->name('payments.waive');
    Route::post('payments/{user}/return-to-payment', [AdminFinanceController::class, 'returnWaiverToPayment'])->name('payments.return-to-payment');
    Route::post('payments/{user}/reset-billing', [AdminFinanceController::class, 'resetBilling'])->name('payments.reset-billing');
    Route::get('payments/{user}/invoice', [AdminFinanceController::class, 'downloadInvoice'])->name('payments.invoice');
    Route::get('payments/{user}/receipt', [AdminFinanceController::class, 'downloadReceipt'])->name('payments.receipt');
    Route::get('export', [AdminFinanceController::class, 'exportReport'])->name('export');
});
