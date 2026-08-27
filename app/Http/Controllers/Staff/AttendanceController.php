<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The attendance log: every badge scan, newest first.
 *
 * The desk's Arrivals panel only keeps the last handful and the register's
 * "Here today" tab is a roster filter, not a record of scans — neither answers
 * "who came through on Tuesday, and at what time". This does. Read-only:
 * attendance is still written only by the check-in app scanning badges.
 */
class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        // Which days actually have scans — the day switcher only offers these.
        $days = Attendance::query()
            ->selectRaw('attendance_date, count(*) as scans')
            ->groupBy('attendance_date')
            ->orderByDesc('attendance_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->attendance_date)->toDateString(),
                'scans' => (int) $row->scans,
            ]);

        // Default to today when it has scans, otherwise the most recent day
        // that does; "all" shows every day at once.
        $requested = (string) $request->input('date', '');
        $validDates = $days->pluck('date')->all();

        $date = match (true) {
            $requested === 'all' => 'all',
            in_array($requested, $validDates, true) => $requested,
            in_array(today()->toDateString(), $validDates, true) => today()->toDateString(),
            default => $validDates[0] ?? today()->toDateString(),
        };

        $search = trim((string) $request->input('search', ''));

        $scans = Attendance::query()
            ->with(['user:id,name,salutation,institution,registration_code', 'staff:id,name,salutation'])
            ->when($date !== 'all', fn (Builder $query) => $query->on($date))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('institution', 'like', "%{$search}%")
                ->orWhere('registration_code', 'like', "%{$search}%")))
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Attendance $scan) => [
                'id' => $scan->id,
                'name' => $scan->user?->full_name ?? '(registrant removed)',
                'institution' => $scan->user?->institution,
                'registration_code' => $scan->user?->registration_code,
                'checked_in_at' => $scan->checked_in_at,
                'date' => $scan->attendance_date?->toDateString(),
                'recorded_by' => $scan->staff?->full_name,
            ]);

        $scoped = Attendance::query()
            ->when($date !== 'all', fn (Builder $query) => $query->on($date))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('institution', 'like', "%{$search}%")
                ->orWhere('registration_code', 'like', "%{$search}%")));

        return Inertia::render('staff/attendance', [
            'scans' => $scans,
            'filters' => [
                'date' => $date,
                'search' => $search ?: null,
            ],
            'days' => $days,
            'summary' => [
                // Scans in the current view, and how many distinct people that
                // is — the same across a single day (one scan each), but not
                // across "all days", where someone attending three days is
                // three scans and one person.
                'scans' => (clone $scoped)->count(),
                'people' => (clone $scoped)->distinct('user_id')->count('user_id'),
                'conference_total' => Attendance::distinct('user_id')->count('user_id'),
            ],
        ]);
    }
}
