<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class BadgeController extends Controller
{
    public function download(QrCodeService $qrCodeService)
    {
        $user = Auth::user();

        abort_unless($user->isPaid(), 403, 'Your payment must be verified before your badge is available.');

        $qr = $qrCodeService->dataUri($user->registration_code);

        $pdf = Pdf::loadView('pdf.badge', [
            'user' => $user,
            'qr' => $qr,
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
            'feeCategoryLabel' => FeeCategory::query()
                ->where('key', $user->fee_category)
                ->value('label') ?? $user->fee_category,
        ])->setPaper([0, 0, 306, 468]); // ~4.25in x 6.5in badge

        return $pdf->download('tmsc-badge-'.$user->registration_code.'.pdf');
    }
}
