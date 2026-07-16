<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingCallbackController;
use App\Http\Controllers\Api\CheckinController;
use Illuminate\Support\Facades\Route;

// NIMR Billing System callbacks — see api.md for the exact payload shapes.
Route::post('billing/control-number-callback', [BillingCallbackController::class, 'controlNumberAssigned'])
    ->name('billing.control-number-callback');
Route::post('billing/payment-callback', [BillingCallbackController::class, 'paymentReceived'])
    ->name('billing.payment-callback');

// Dev-only: simulate a successful payment while sandbox mode is on.
Route::post('billing/sandbox/simulate/{user}', [BillingCallbackController::class, 'simulate'])
    ->name('billing.sandbox.simulate');

// Staff check-in app. Login issues a Sanctum token restricted to admin/staff
// accounts; every other endpoint here requires that token.
Route::post('checkin/login', [AuthController::class, 'login'])
    ->middleware('throttle:checkin-login')
    ->name('checkin.login');

Route::middleware('auth:sanctum')->prefix('checkin')->name('checkin.')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('register', [CheckinController::class, 'register'])->name('register');
    Route::post('scan', [CheckinController::class, 'scan'])->name('scan');
    Route::get('lookup', [CheckinController::class, 'lookup'])->name('lookup');
    Route::post('users/{user}/check-in', [CheckinController::class, 'checkInById'])->name('check-in-by-id');
    Route::get('recent', [CheckinController::class, 'recent'])->name('recent');
});
