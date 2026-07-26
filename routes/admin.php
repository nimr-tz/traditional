<?php

use App\Http\Controllers\Admin\AbstractController as AdminAbstractController;
use App\Http\Controllers\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Admin\BillingMaintenanceController as AdminBillingMaintenanceController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
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
    Route::get('abstracts/{abstract}/presentation/download', [AdminAbstractController::class, 'downloadPresentation'])->name('abstracts.presentation.download');
    Route::post('abstracts/{abstract}/presentation/approve', [AdminAbstractController::class, 'approvePresentation'])->name('abstracts.presentation.approve');
    Route::post('abstracts/{abstract}/presentation/reject', [AdminAbstractController::class, 'rejectPresentation'])->name('abstracts.presentation.reject');
});

// Registrations, student verification, reviewer assignment, and final abstract decisions:
// day-to-day conference operations, open to admin and super_admin alike.
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('abstracts/{abstract}/reviewers', [AdminAbstractController::class, 'assignReviewers'])->name('abstracts.reviewers.assign');
    Route::post('abstracts/{abstract}/decide', [AdminAbstractController::class, 'decide'])->name('abstracts.decide');

    Route::get('registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
    Route::get('registrations/export', [AdminRegistrationController::class, 'export'])->name('registrations.export');

    Route::get('students', [AdminStudentVerificationController::class, 'index'])->name('students.index');
    Route::get('students/{user}/document', [AdminStudentVerificationController::class, 'document'])->name('students.document');
    Route::post('students/{user}/verify', [AdminStudentVerificationController::class, 'verify'])->name('students.verify');
    Route::post('students/{user}/reject', [AdminStudentVerificationController::class, 'reject'])->name('students.reject');
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

    Route::get('settings/users', [AdminAdministratorController::class, 'index'])->name('settings.users.index');
    Route::patch('settings/users/{user}/roles', [AdminAdministratorController::class, 'updateRoles'])->name('settings.users.update-roles');

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
