<?php

use App\Http\Controllers\AbstractController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BadgeVerificationController;
use App\Http\Controllers\CertificateClaimController;
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

// What a badge's QR opens in an ordinary phone camera. Public on purpose: a
// volunteer on a door with no staff login still needs to verify a badge. The
// same QR scanned by the check-in app records attendance instead.
Route::get('badges/verify/{code}', [BadgeVerificationController::class, 'show'])->name('badges.verify');

// Claiming a certificate without an account. Walk-ins registered at the desk
// have a password nobody told them and often no email at all, so requiring a
// sign-in would exclude the people most likely to need this.
Route::get('certificate/claim', [CertificateClaimController::class, 'create'])->name('certificate.claim');
Route::post('certificate/claim', [CertificateClaimController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('certificate.claim.submit');

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

    Route::get('presentations', [PresentationController::class, 'index'])->name('presentations.index');

    Route::get('abstracts/{abstract}/presentation', [PresentationController::class, 'show'])->name('abstracts.presentation.show');
    Route::post('abstracts/{abstract}/presentation', [PresentationController::class, 'store'])->name('abstracts.presentation.store');
    Route::delete('abstracts/{abstract}/presentation', [PresentationController::class, 'destroy'])->name('abstracts.presentation.destroy');
    Route::get('abstracts/{abstract}/presentation/download', [PresentationController::class, 'download'])->name('abstracts.presentation.download');

    // Self-service badge. Only reachable once the payment is verified or
    // waived — BadgeController redirects back to the payment page otherwise.
    Route::get('badge', [BadgeController::class, 'download'])->name('badge.download');

    // A page rather than a bare link: most of the time there is something to
    // explain (not unlocked yet, no attendance recorded) and a link that just
    // fails tells the holder nothing.
    Route::get('certificate', [CertificateController::class, 'show'])->name('certificate.show');
    Route::get('certificate/download', [CertificateController::class, 'download'])->name('certificate.download');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
