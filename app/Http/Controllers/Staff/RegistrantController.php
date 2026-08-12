<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The full register of everyone attending, for browsing rather than answering.
 *
 * Separate from the desk on purpose. The desk opens on a search because nobody
 * works a door by scrolling four hundred names; this page is for the other job
 * — seeing the whole picture, working through who has not arrived, checking a
 * category's numbers, printing a batch of badges before doors open.
 *
 * Read-only apart from badge printing. Attendance is still recorded only by the
 * check-in app scanning badges, so there is one account of who walked in.
 */
class RegistrantController extends Controller
{
    /** The filters the page offers, and how each narrows the register. */
    private const FILTERS = ['all', 'here_today', 'not_arrived', 'unpaid', 'complimentary', 'never_attended'];

    private function registrants(): Builder
    {
        return User::withRole(User::ROLE_USER);
    }

    public function index(Request $request): Response
    {
        $status = in_array($request->status, self::FILTERS, true) ? $request->status : 'all';
        $category = $request->category && $request->category !== 'all' ? $request->category : null;

        $people = $this->registrants()
            ->with('attendance:id,user_id,attendance_date,checked_in_at')
            ->withCount('badgePrints')
            ->when($request->search, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('institution', 'like', "%{$search}%")
                ->orWhere('registration_code', 'like', "%{$search}%")
                ->orWhere('control_number', 'like', "%{$search}%")))
            ->when($category, fn (Builder $query) => $query->where('fee_category', $category))
            ->tap(fn (Builder $query) => $this->applyStatus($query, $status))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'institution' => $user->institution,
                'fee_category' => $user->fee_category,
                'is_paid' => $user->isPaid(),
                'payment_status' => $user->payment_status,
                'fee_amount' => $user->fee_amount,
                'currency' => $user->currency,
                'control_number' => $user->control_number,
                'registration_code' => $user->registration_code,
                'checked_in_at' => $user->attendance->firstWhere(fn ($a) => $a->attendance_date?->isToday())?->checked_in_at,
                'days_attended' => $user->attendance->count(),
                'last_seen_at' => $user->attendance->sortByDesc('checked_in_at')->first()?->checked_in_at,
                'can_print_badge' => $user->canPrintBadge(),
                'badges_printed' => $user->badge_prints_count ?? 0,
            ]);

        return Inertia::render('staff/registrants', [
            'people' => $people,
            'filters' => [
                'search' => $request->search,
                'status' => $status,
                'category' => $category ?? 'all',
            ],
            'counts' => $this->counts(),
            'categories' => FeeCategory::query()
                ->active()
                ->orderBy('is_complimentary')
                ->orderBy('sort_order')
                ->get(['key', 'label', 'is_complimentary']),
        ]);
    }

    private function applyStatus(Builder $query, string $status): void
    {
        match ($status) {
            'here_today' => $query->whereHas('attendance', fn (Builder $a) => $a->today()),
            'not_arrived' => $query
                ->whereIn('payment_status', ['verified', 'waived'])
                ->whereDoesntHave('attendance', fn (Builder $a) => $a->today()),
            'unpaid' => $query->whereNotIn('payment_status', ['verified', 'waived']),
            'complimentary' => $query->whereIn(
                'fee_category',
                FeeCategory::where('is_complimentary', true)->pluck('key')
            ),
            'never_attended' => $query
                ->whereIn('payment_status', ['verified', 'waived'])
                ->whereDoesntHave('attendance'),
            default => null,
        };
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $paid = fn () => $this->registrants()->whereIn('payment_status', ['verified', 'waived']);

        return [
            'all' => $this->registrants()->count(),
            'here_today' => (clone $paid())->whereHas('attendance', fn (Builder $a) => $a->today())->count(),
            'not_arrived' => (clone $paid())->whereDoesntHave('attendance', fn (Builder $a) => $a->today())->count(),
            'unpaid' => $this->registrants()->whereNotIn('payment_status', ['verified', 'waived'])->count(),
            'complimentary' => $this->registrants()->whereIn(
                'fee_category',
                FeeCategory::where('is_complimentary', true)->pluck('key')
            )->count(),
            'never_attended' => (clone $paid())->whereDoesntHave('attendance')->count(),
        ];
    }
}
