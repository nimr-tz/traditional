<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\ConferenceSetting;
use App\Models\User;
use App\Support\CertificateWindow;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use RuntimeException;

/**
 * Issues a certificate of participation, and decides who is entitled to one.
 *
 * Shared by the registrant's own page and the public claim that walk-ins use,
 * so the two cannot drift on eligibility. The rules are all here:
 *
 *  - the release time has passed (see CertificateWindow),
 *  - the registration is settled, and
 *  - they actually attended at least one day.
 *
 * The last one is the substance of the document. A certificate is a statement
 * that somebody was there; issuing one to a registrant who never came would
 * make it worthless.
 */
class CertificateIssuer
{
    public function __construct(private QrCodeService $qrCodes) {}

    /** Why this person cannot have a certificate yet, or null if they can. */
    public function blockedReason(User $user): ?string
    {
        if (! CertificateWindow::isOpen()) {
            return CertificateWindow::pendingMessage();
        }

        if (! $user->isPaid()) {
            return 'Your registration must be settled before a certificate can be issued.';
        }

        if (! $user->isCheckedIn()) {
            return 'Certificates are issued to attendees who were checked in at the conference. We have no attendance recorded against this registration.';
        }

        return null;
    }

    public function isEligible(User $user): bool
    {
        return $this->blockedReason($user) === null;
    }

    /**
     * @throws RuntimeException when they are not entitled to one
     */
    public function render(User $user): PdfDocument
    {
        if ($reason = $this->blockedReason($user)) {
            throw new RuntimeException($reason);
        }

        $certificate = Certificate::firstOrCreate(
            ['user_id' => $user->id],
            ['certificate_code' => Certificate::generateCode(), 'issued_at' => now()],
        );

        return Pdf::loadView('pdf.certificate', [
            'user' => $user,
            'certificate' => $certificate,
            'daysAttended' => $user->attendance()->count(),
            'qr' => $this->qrCodes->dataUri(route('certificates.verify', $certificate->certificate_code)),
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
        ])->setPaper('a4', 'landscape');
    }

    public function filenameFor(User $user): string
    {
        $certificate = Certificate::where('user_id', $user->id)->first();

        return 'tmsc-certificate-'.($certificate?->certificate_code ?? $user->registration_code).'.pdf';
    }
}
