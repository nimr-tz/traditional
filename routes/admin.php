<?php

use App\Http\Controllers\Admin\AbstractController as AdminAbstractController;
use App\Http\Controllers\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
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

// Registrations, student verification, settings, and final abstract decisions stay admin/super-admin only.
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('abstracts/{abstract}/reviewers', [AdminAbstractController::class, 'assignReviewers'])->name('abstracts.reviewers.assign');
    Route::post('abstracts/{abstract}/decide', [AdminAbstractController::class, 'decide'])->name('abstracts.decide');

    Route::get('registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
    Route::get('registrations/export', [AdminRegistrationController::class, 'export'])->name('registrations.export');

    Route::get('students', [AdminStudentVerificationController::class, 'index'])->name('students.index');
    Route::get('students/{user}/document', [AdminStudentVerificationController::class, 'document'])->name('students.document');
    Route::post('students/{user}/verify', [AdminStudentVerificationController::class, 'verify'])->name('students.verify');
    Route::post('students/{user}/reject', [AdminStudentVerificationController::class, 'reject'])->name('students.reject');

    Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings/fee-categories/{feeCategory}', [AdminSettingsController::class, 'updateFeeCategory'])->name('settings.fee-categories.update');
    Route::post('settings/subthemes', [AdminSettingsController::class, 'storeSubtheme'])->name('settings.subthemes.store');
    Route::patch('settings/subthemes/{subtheme}', [AdminSettingsController::class, 'updateSubtheme'])->name('settings.subthemes.update');
    Route::post('settings/institutions', [AdminSettingsController::class, 'storeInstitution'])->name('settings.institutions.store');
    Route::patch('settings/institutions/{institution}', [AdminSettingsController::class, 'updateInstitution'])->name('settings.institutions.update');
    Route::patch('settings/conference', [AdminSettingsController::class, 'updateConferenceSettings'])->name('settings.conference.update');

    // Admins can grant or revoke any role, including other admins — kept out of the reviewer group above.
    Route::get('settings/administrators/search', [AdminAdministratorController::class, 'search'])->name('settings.administrators.search');
    Route::post('settings/administrators', [AdminAdministratorController::class, 'store'])->name('settings.administrators.store');
    Route::delete('settings/administrators/{user}', [AdminAdministratorController::class, 'destroy'])->name('settings.administrators.destroy');
});
