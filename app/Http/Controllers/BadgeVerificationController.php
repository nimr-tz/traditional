<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\View\View;

/**
 * The page a badge's QR code opens in an ordinary phone camera.
 *
 * The same QR serves two readers. Scanned by the check-in app it records the
 * day's attendance; scanned by anyone else it lands here and answers the
 * question a steward actually has — is this person registered, and have they
 * been attending?
 *
 * Public and unauthenticated by design: the point is that a volunteer on a door
 * with no staff login can still verify a badge. It therefore shows nothing that
 * is not already printed on the front of that badge — name, institution,
 * category — plus whether the registration is live. No email, no phone, no
 * payment detail. A lost badge must not become a data leak.
 */
class BadgeVerificationController extends Controller
{
    public function show(string $code): View
    {
        $user = User::withRole(User::ROLE_USER)
            ->where('registration_code', $code)
            ->first();

        // Only a genuinely settled registration counts as valid. Someone whose
        // payment was later reset should not keep a working badge.
        $valid = $user !== null && $user->isPaid();

        return view('badges.verify', [
            'code' => $code,
            'valid' => $valid,
            'registrant' => $valid ? [
                'name' => trim(($user->salutation ? $user->salutation.' ' : '').$user->name),
                'institution' => $user->institution,
                'category' => FeeCategory::where('key', $user->fee_category)->value('label') ?? $user->fee_category,
                'days_attended' => $user->attendance()->count(),
                'attended_today' => $user->isCheckedInToday(),
                'last_attended_at' => $user->attendance()->latest('checked_in_at')->value('checked_in_at'),
            ] : null,
            'conferenceName' => ConferenceSetting::get('conference_name', config('app.name')),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
            'venue' => ConferenceSetting::get('venue'),
        ]);
    }
}
