<?php

namespace App\Services;

use App\Models\BadgePrintLog;
use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use RuntimeException;

/**
 * Produces the badge PDF, and records that it was produced.
 *
 * Shared by the registrant downloading their own and the desk printing one for
 * somebody, so the two cannot drift on what a badge looks like or on whether
 * the print was logged. Every route into this class writes a BadgePrintLog row:
 * a badge is entry, and an unlogged reprint is an untracked second way in.
 */
class BadgePrinter
{
    public function __construct(private QrCodeService $qrCodes) {}

    /**
     * @throws RuntimeException when the registrant has no right to a badge
     */
    public function render(User $user, ?User $printedBy = null): PdfDocument
    {
        if (! $user->canPrintBadge()) {
            throw new RuntimeException(
                "No badge for {$user->name}: a badge requires a verified or waived payment."
            );
        }

        // Paid, but from before the code was minted on payment — or settled by a
        // path that predates that rule. Mint it now rather than refusing them a
        // badge over it. generateRegistrationCode() re-checks payment itself, so
        // this cannot hand a code to somebody who has not paid.
        if (blank($user->registration_code)) {
            $user->generateRegistrationCode();
            $user->save();
        }

        $category = $this->categoryLabel($user);

        BadgePrintLog::record($user, $printedBy, $user->name, $user->institution, $category);

        $template = config('badge.template');

        $pdf = Pdf::loadView('pdf.badge', [
            'name' => trim(($user->salutation ? $user->salutation.' ' : '').$user->name),
            'institution' => $user->institution,
            'categoryLabel' => $category,
            'registrationCode' => $user->registration_code,
            // A URL, not the bare code, so the same square serves two readers:
            // an ordinary phone camera opens the public verification page, while
            // the check-in app pulls the code back out of it and records the
            // day's attendance. See Api\CheckinController::scan().
            'qr' => $this->qrCodes->dataUri(route('badges.verify', $user->registration_code)),
            'conferenceName' => ConferenceSetting::get('conference_name', config('app.name')),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
        ]);

        // Millimetres, because badge stock is bought in millimetres. dompdf
        // takes points, so convert rather than leaving a magic number here.
        $pdf->setPaper([0, 0, $this->mmToPoints($template['width_mm']), $this->mmToPoints($template['height_mm'])]);

        return $pdf;
    }

    public function filenameFor(User $user): string
    {
        return 'tmsc-badge-'.$user->registration_code.'.pdf';
    }

    /**
     * What the badge says about why this person is here.
     *
     * For a complimentary registrant this is the whole justification for their
     * presence — "Media", "Secretariat" — so it must be the category's own
     * label and never a generic "waived".
     */
    private function categoryLabel(User $user): ?string
    {
        return FeeCategory::where('key', $user->fee_category)->value('label') ?? $user->fee_category;
    }

    private function mmToPoints(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }
}
