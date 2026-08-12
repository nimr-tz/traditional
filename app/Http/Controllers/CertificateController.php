<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\ConferenceSetting;
use App\Services\CertificateIssuer;
use App\Support\CertificateWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CertificateController extends Controller
{
    /**
     * The registrant's own certificate page.
     *
     * A page rather than a bare download link, because most of the time there
     * is something to explain — it has not unlocked yet, or no attendance is
     * recorded — and a link that simply fails tells them nothing.
     */
    public function show(CertificateIssuer $issuer): InertiaResponse
    {
        $user = Auth::user();

        return Inertia::render('certificate', [
            'window' => CertificateWindow::toArray(),
            'blockedReason' => $issuer->blockedReason($user),
            'daysAttended' => $user->attendance()->count(),
            'isPaid' => $user->isPaid(),
            'certificateCode' => Certificate::where('user_id', $user->id)->value('certificate_code'),
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
        ]);
    }

    public function download(CertificateIssuer $issuer): Response|RedirectResponse
    {
        $user = Auth::user();

        if ($reason = $issuer->blockedReason($user)) {
            return redirect()->route('certificate.show')->with('error', $reason);
        }

        return $issuer->render($user)->download($issuer->filenameFor($user));
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
