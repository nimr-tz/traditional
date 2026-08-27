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
     * Everything printed on a badge, without printing one.
     *
     * Split out from render() so the desk can show a registrant their badge on
     * screen without that counting as a print: render() logs a BadgePrintLog
     * every time it runs, and a preview that logged would make the next real
     * print announce itself as a reprint. Anything that puts a badge in front of
     * a human — the PDF or the screen — reads its content from here, so the two
     * cannot drift on what a badge says.
     *
     * @return array<string, string|null>
     *
     * @throws RuntimeException when the registrant has no right to a badge
     */
    public function content(User $user): array
    {
        if (! $user->canPrintBadge()) {
            throw new RuntimeException(
                "No badge for {$user->full_name}: a badge requires a verified or waived payment."
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

        return [
            'name' => trim(($user->salutation ? $user->salutation.' ' : '').$user->name),
            // Carries the dignitary's role when they have one — "DIRECTOR
            // GENERAL, MUHAS" — and is plain "MUHAS" otherwise. Same slot on
            // the card either way.
            'institution' => $user->badge_affiliation,
            'registrationCode' => $user->registration_code,
            // A URL, not the bare code, so the same square serves two readers:
            // an ordinary phone camera opens the public verification page, while
            // the check-in app pulls the code back out of it and records the
            // day's attendance. See Api\CheckinController::scan().
            'qr' => $this->qrCodes->dataUri(route('badges.verify', $user->registration_code)),
            'conferenceName' => ConferenceSetting::get('conference_name', config('app.name')),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
        ];
    }

    /**
     * The same badge, on screen instead of on paper.
     *
     * Returns null rather than throwing for someone who has not paid: callers
     * are pages deciding whether there is a badge to show yet, and "not paid"
     * is an ordinary answer there, not an error.
     *
     * @return array<string, mixed>|null
     */
    public function preview(User $user): ?array
    {
        if (! $user->canPrintBadge()) {
            return null;
        }

        return [
            ...$this->content($user),
            // The screen badge is positioned from the same percentages as the
            // PDF, so re-skinning the badge moves both at once instead of
            // leaving the preview behind.
            'template' => config('badge.template'),
            'placeholders' => config('badge.placeholders'),
            // Encoded segment by segment: the artwork ships under its designer's
            // own filename, spaces and all, and an unencoded space is not a
            // valid URL. The PDF reads the same value straight off disk via
            // public_path(), where spaces are fine.
            'background' => asset(implode('/', array_map(
                'rawurlencode',
                explode('/', config('badge.template.background'))
            ))),
        ];
    }

    /**
     * @throws RuntimeException when the registrant has no right to a badge
     */
    public function render(User $user, ?User $printedBy = null): PdfDocument
    {
        $content = $this->content($user);

        BadgePrintLog::record($user, $printedBy, $user->full_name, $user->badge_affiliation, $this->categoryLabel($user));

        $template = config('badge.template');

        $pdf = Pdf::loadView('pdf.badge', $content);

        // Millimetres, because badge stock is bought in millimetres. dompdf
        // takes points, so convert rather than leaving a magic number here.
        $pdf->setPaper([0, 0, $this->mmToPoints($template['width_mm']), $this->mmToPoints($template['height_mm'])]);

        return $pdf;
    }

    /**
     * A run of badges as one PDF, one card per page.
     *
     * The desk prints these before doors open for everyone matching a filter.
     * Callers must pass only people entitled to a badge — content() throws
     * otherwise, aborting the whole run rather than quietly skipping someone.
     * Each card is logged exactly as a single print is: a batch is still a
     * stack of badges leaving the printer.
     *
     * @param  iterable<int, User>  $users
     *
     * @throws RuntimeException when any registrant in the run has no right to a badge
     */
    public function renderBatch(iterable $users, ?User $printedBy = null): PdfDocument
    {
        $template = config('badge.template');
        $badges = [];

        foreach ($users as $user) {
            $badges[] = $this->content($user);

            BadgePrintLog::record($user, $printedBy, $user->full_name, $user->badge_affiliation, $this->categoryLabel($user));
        }

        $pdf = Pdf::loadView('pdf.badges', [
            'badges' => $badges,
            'widthMm' => $template['width_mm'],
            'heightMm' => $template['height_mm'],
        ]);

        $pdf->setPaper([0, 0, $this->mmToPoints($template['width_mm']), $this->mmToPoints($template['height_mm'])]);

        return $pdf;
    }

    public function filenameFor(User $user): string
    {
        return 'tmsc-badge-'.$user->registration_code.'.pdf';
    }

    public function filenameForBatch(int $count): string
    {
        return 'tmsc-badges-'.$count.'-'.now()->format('Y-m-d').'.pdf';
    }

    /**
     * The registrant's actual fee category, for the print log only.
     *
     * Nothing prints it on the badge — the artwork has no band for it. The log
     * is a record of who was issued a badge rather than of what the face showed,
     * so it keeps the category even though the card does not.
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
