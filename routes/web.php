<?php

use App\Http\Controllers\AbstractController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PresentationController;
use App\Http\Controllers\StudentVerificationController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::get('readyz', function () {
    DB::select('SELECT 1');

    abort_unless(is_writable(storage_path()), 503, 'Storage is not writable.');

    return response('ok', 200)->header('Content-Type', 'text/plain');
})->name('ready');

Route::get('sitemap.xml', function () {
    return response()
        ->view('sitemap', ['homeUrl' => route('home')])
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('certificates/verify/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('payment', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('payment/control-number', [PaymentController::class, 'requestControlNumber'])->name('payment.control-number');
    Route::get('payment/status', [PaymentController::class, 'pollStatus'])->name('payment.status');
    Route::get('payment/invoice', [PaymentController::class, 'downloadInvoice'])->name('payment.invoice');
    Route::get('payment/receipt', [PaymentController::class, 'downloadReceipt'])->name('payment.receipt');
    Route::post('student-verification/document', [StudentVerificationController::class, 'store'])
        ->name('student-verification.document');

    Route::get('abstracts', [AbstractController::class, 'index'])->name('abstracts.index');
    Route::get('abstracts/create', [AbstractController::class, 'create'])->name('abstracts.create');
    Route::post('abstracts', [AbstractController::class, 'store'])->name('abstracts.store');
    Route::get('abstracts/{abstract}/edit', [AbstractController::class, 'edit'])->name('abstracts.edit');
    Route::put('abstracts/{abstract}', [AbstractController::class, 'update'])->name('abstracts.update');
    Route::post('abstracts/{abstract}/comments/{comment}/toggle-addressed', [AbstractController::class, 'toggleCommentAddressed'])
        ->name('abstracts.comments.toggle-addressed');

    Route::get('abstracts/{abstract}/presentation', [PresentationController::class, 'show'])->name('abstracts.presentation.show');
    Route::post('abstracts/{abstract}/presentation', [PresentationController::class, 'store'])->name('abstracts.presentation.store');
    Route::delete('abstracts/{abstract}/presentation', [PresentationController::class, 'destroy'])->name('abstracts.presentation.destroy');
    Route::get('abstracts/{abstract}/presentation/download', [PresentationController::class, 'download'])->name('abstracts.presentation.download');

    Route::get('certificate', [CertificateController::class, 'download'])->name('certificate.download');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
