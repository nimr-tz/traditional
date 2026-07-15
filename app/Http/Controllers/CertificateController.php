<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\ConferenceSetting;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function download(QrCodeService $qrCodeService)
    {
        $user = Auth::user();

        abort_unless($user->isPaid(), 403, 'Your payment must be verified first.');
        abort_unless($user->isCheckedIn(), 403, 'Your certificate becomes available once you have checked in at the conference.');

        $certificate = Certificate::firstOrCreate(
            ['user_id' => $user->id],
            ['certificate_code' => Certificate::generateCode(), 'issued_at' => now()]
        );

        $qr = $qrCodeService->dataUri(url("/certificates/verify/{$certificate->certificate_code}"));

        $pdf = Pdf::loadView('pdf.certificate', [
            'user' => $user,
            'certificate' => $certificate,
            'qr' => $qr,
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('tmsc-certificate-'.$certificate->certificate_code.'.pdf');
    }

    public function verify(string $code): View
    {
        $certificate = Certificate::with('user')->where('certificate_code', $code)->first();

        return view('certificates.verify', [
            'certificate' => $certificate,
            'conferenceName' => ConferenceSetting::get('conference_name'),
        ]);
    }
}
