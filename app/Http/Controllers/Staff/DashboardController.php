<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ConferenceSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The venue desk's view of who is registered and where each of them stands.
 *
 * Check-in staff previously landed on the registrant dashboard, which asked
 * them to pay a conference fee and submit an abstract — none of which applies
 * to someone working the door.
 *
 * Deliberately read-only: recording attendance belongs to the check-in app,
 * which scans badges against /api/checkin/*. Two consoles that could both write
 * attendance would only disagree about who arrived. What the desk needs here is
 * the picture, so every registrant is listed with the one thing that decides
 * what happens next — paid or not, arrived or not.
 */
class DashboardController extends Controller
{
    /** Registrants only; staff and organisers are not people who come through the door. */
    private function registrants(): Builder
    {
        return User::withRole(User::ROLE_USER);
    }

    public function index(Request $request): Response
    {
        $staff = Auth::user();

        // Only paid registrants hold a badge, so they are the population the
        // door is expecting — the same rule the check-in endpoint enforces.
        $expectedCount = $this->registrants()->whereIn('payment_status', ['verified', 'waived'])->count();
        $checkedIn = $this->registrants()
            ->whereIn('payment_status', ['verified', 'waived'])
            ->whereHas('attendance')
            ->count();

        $status = in_array($request->status, ['arrived', 'not_arrived', 'unpaid'], true) ? $request->status : 'all';

        $people = $this->registrants()
            ->with('attendance:id,user_id,checked_in_at')
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('institution', 'like', "%{$search}%")
                ->orWhere('registration_code', 'like', "%{$search}%")))
            ->when($status === 'arrived', fn (Builder $query) => $query->whereHas('attendance'))
            ->when($status === 'not_arrived', fn (Builder $query) => $query
                ->whereIn('payment_status', ['verified', 'waived'])
                ->whereDoesntHave('attendance'))
            ->when($status === 'unpaid', fn (Builder $query) => $query
                ->whereNotIn('payment_status', ['verified', 'waived']))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'institution' => $user->institution,
                'participant_type' => $user->participant_type,
                'is_paid' => $user->isPaid(),
                'payment_status' => $user->payment_status,
                'fee_amount' => $user->fee_amount,
                'currency' => $user->currency,
                'registration_code' => $user->registration_code,
                'checked_in_at' => $user->attendance->first()?->checked_in_at,
            ]);

        return Inertia::render('staff/dashboard', [
            'staffName' => $staff->name,
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'venue' => ConferenceSetting::get('venue'),
            'people' => $people,
            'filters' => [
                'search' => $request->search,
                'status' => $status,
            ],
            'stats' => [
                'registered' => $this->registrants()->count(),
                'expected' => $expectedCount,
                'checked_in' => $checkedIn,
                'today' => Attendance::whereDate('checked_in_at', today())->count(),
                'not_arrived' => max(0, $expectedCount - $checkedIn),
                'unpaid' => $this->registrants()->whereNotIn('payment_status', ['verified', 'waived'])->count(),
                'recorded_by_me' => Attendance::where('checked_in_by', $staff->id)->count(),
            ],
            'recent' => Attendance::with(['user:id,name,institution', 'staff:id,name'])
                ->latest('checked_in_at')
                ->limit(10)
                ->get()
                ->map(fn (Attendance $attendance) => [
                    'id' => $attendance->id,
                    'checked_in_at' => $attendance->checked_in_at,
                    'name' => $attendance->user?->name,
                    'institution' => $attendance->user?->institution,
                    'recorded_by' => $attendance->staff?->name,
                ]),
        ]);
    }
}
