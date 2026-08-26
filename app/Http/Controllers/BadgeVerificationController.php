<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSetting;
use App\Models\User;
use Illuminate\View\View;

/**
 * The page a badge's QR code opens in an ordinary phone camera.
 *
 * The same QR serves two readers. Scanned by the check-in app it records the
 * day's attendance; scanned by anyone else it lands here.
 *
 * This is written as a record of participation rather than a door readout. A
 * badge outlives the conference: the person who kept theirs may open this years
 * later, and someone verifying a CV may open it during an interview. Both want
 * the same few facts — who, which conference, where, when, and whether NIMR
 * stands behind it — so operational noise ("not yet scanned today") is left to
 * the staff app, where it belongs.
 *
 * It distinguishes attending from registering, and never claims the stronger of
 * the two. A page that would confirm attendance for somebody who never walked
 * through the door is worth nothing to the person holding an honest badge.
 *
 * Public and unauthenticated by design: a volunteer on a door with no staff
 * login can still verify a badge. It therefore shows nothing that was not
 * already printed on the front of that badge — name and institution. No email,
 * no phone, no payment detail, and no fee category: what somebody paid is
 * nobody's business at a door or in an interview. A lost badge must not become
 * a data leak.
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

        $attendance = $valid
            ? $user->attendance()->orderBy('checked_in_at')->get()
            : collect();

        return view('badges.verify', [
            'code' => $code,
            'valid' => $valid,
            'registrant' => $valid ? [
                'name' => trim(($user->salutation ? $user->salutation.' ' : '').$user->name),
                'institution' => $user->institution,
                'days_attended' => $attendance->count(),
                'first_attended_at' => $attendance->first()?->checked_in_at,
                'last_attended_at' => $attendance->last()?->checked_in_at,
            ] : null,
            'conference' => [
                'name' => ConferenceSetting::get('conference_name', config('app.name')),
                'edition' => ConferenceSetting::get('edition_number'),
                'year' => ConferenceSetting::get('conference_year'),
                'venue' => ConferenceSetting::get('venue'),
                'start_date' => ConferenceSetting::get('start_date'),
                'end_date' => ConferenceSetting::get('end_date'),
            ],
        ]);
    }
}
