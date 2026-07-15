<?php

use App\Http\Controllers\Admin\AbstractController as AdminAbstractController;
use App\Http\Controllers\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\StudentVerificationController as AdminStudentVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
    Route::get('registrations/export', [AdminRegistrationController::class, 'export'])->name('registrations.export');

    Route::get('students', [AdminStudentVerificationController::class, 'index'])->name('students.index');
    Route::get('students/{user}/document', [AdminStudentVerificationController::class, 'document'])->name('students.document');
    Route::post('students/{user}/verify', [AdminStudentVerificationController::class, 'verify'])->name('students.verify');
    Route::post('students/{user}/reject', [AdminStudentVerificationController::class, 'reject'])->name('students.reject');

    Route::get('abstracts', [AdminAbstractController::class, 'index'])->name('abstracts.index');
    Route::get('abstracts/{abstract}', [AdminAbstractController::class, 'show'])->name('abstracts.show');
    Route::post('abstracts/{abstract}/decide', [AdminAbstractController::class, 'decide'])->name('abstracts.decide');

    Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::get('settings/administrators/search', [AdminAdministratorController::class, 'search'])->name('settings.administrators.search');
    Route::post('settings/administrators', [AdminAdministratorController::class, 'store'])->name('settings.administrators.store');
    Route::delete('settings/administrators/{user}', [AdminAdministratorController::class, 'destroy'])->name('settings.administrators.destroy');
    Route::patch('settings/fee-categories/{feeCategory}', [AdminSettingsController::class, 'updateFeeCategory'])->name('settings.fee-categories.update');
    Route::post('settings/subthemes', [AdminSettingsController::class, 'storeSubtheme'])->name('settings.subthemes.store');
    Route::patch('settings/subthemes/{subtheme}', [AdminSettingsController::class, 'updateSubtheme'])->name('settings.subthemes.update');
    Route::post('settings/institutions', [AdminSettingsController::class, 'storeInstitution'])->name('settings.institutions.store');
    Route::patch('settings/institutions/{institution}', [AdminSettingsController::class, 'updateInstitution'])->name('settings.institutions.update');
    Route::patch('settings/conference', [AdminSettingsController::class, 'updateConferenceSettings'])->name('settings.conference.update');
});
