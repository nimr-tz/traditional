<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use App\Models\User;
use App\Support\FeeTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reporting view of the conference: totals and breakdowns for the organizing
 * committee, as opposed to /admin which is the day-to-day work queue.
 *
 * Everything here counts *registrants* — accounts holding the plain `user`
 * role. Staff, reviewers, and admins have accounts too, and folding them into
 * "all registered" would inflate every headline number the committee reports.
 */
class ManagementDashboardController extends Controller
{
    private const STUDENT_CATEGORIES = ['student_east_africa', 'student_non_east_africa'];

    public function index(): Response
    {
        return Inertia::render('admin/management/index', [
            'totals' => $this->totals(),
            'abstracts' => $this->abstractBreakdown(),
            'byCountry' => $this->registrationsByCountry(),
            'byInstitution' => $this->registrationsByInstitution(),
            'abstractsByCountry' => $this->abstractsByCountry(),
        ]);
    }

    private function registrants(): Builder
    {
        return User::query()->withRole(User::ROLE_USER);
    }

    /** @return array<string, int> */
    private function totals(): array
    {
        $paid = fn (Builder $q) => $q->whereIn('payment_status', ['verified', 'waived']);

        return [
            // Headline: every registrant account, whatever their payment state.
            'registered' => $this->registrants()->count(),
            // "Paid" includes waived — the fee is settled either way, and a
            // waived delegate still counts against the venue headcount.
            'paid' => $paid($this->registrants())->count(),
            'unpaid' => $this->registrants()->whereNotIn('payment_status', ['verified', 'waived'])->count(),
            'awaiting_payment' => $this->registrants()->where('payment_status', 'submitted')->count(),
            'with_abstracts' => $this->registrants()->whereHas('abstractSubmissions')->count(),
            'paid_with_abstracts' => $paid($this->registrants())->whereHas('abstractSubmissions')->count(),
            'students' => $this->registrants()->whereIn('fee_category', self::STUDENT_CATEGORIES)->count(),
            'non_students' => $this->registrants()->whereNotIn('fee_category', self::STUDENT_CATEGORIES)->count(),
            'east_africa' => $this->registrants()->where('is_east_africa', true)->count(),
            'international' => $this->registrants()->where('is_east_africa', false)->count(),
            'checked_in' => $this->registrants()->whereHas('attendance')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function abstractBreakdown(): array
    {
        $byColumn = fn (string $column) => AbstractSubmission::query()
            ->selectRaw("{$column} as label, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'label');

        $types = $byColumn('presentation_type');
        $statuses = $byColumn('status');

        return [
            'total' => AbstractSubmission::count(),
            'oral' => (int) ($types['oral'] ?? 0),
            'poster' => (int) ($types['poster'] ?? 0),
            'submitted' => (int) ($statuses['submitted'] ?? 0),
            'accepted' => (int) ($statuses['accepted'] ?? 0),
            'revision_requested' => (int) ($statuses['revision_requested'] ?? 0),
            'rejected' => (int) ($statuses['rejected'] ?? 0),
            'by_subtheme' => AbstractSubmission::query()
                ->leftJoin('subthemes', 'subthemes.id', '=', 'abstract_submissions.subtheme_id')
                ->selectRaw('coalesce(subthemes.title, ?) as label, count(*) as total', ['No sub-theme'])
                ->groupBy('label')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
                ->values(),
        ];
    }

    /**
     * Registrants grouped by country, with the paid split so the committee can
     * see conversion per market rather than raw interest.
     */
    private function registrationsByCountry(): Collection
    {
        return $this->registrants()
            ->selectRaw("coalesce(nullif(country, ''), 'Not stated') as label")
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when payment_status in ('verified','waived') then 1 else 0 end) as paid")
            ->selectRaw('max(is_east_africa) as is_east_africa')
            ->groupBy('label')
            ->orderByDesc('total')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'paid' => (int) $row->paid,
                'region' => $row->is_east_africa ? FeeTier::EAST_AFRICA : FeeTier::INTERNATIONAL,
            ])
            ->values();
    }

    /**
     * Grouped on the denormalised `institution` string rather than
     * `institution_id`, because registrants who picked "Other" have a free-text
     * name and no id — leaving them out would hide exactly the organisations
     * not yet on the official list.
     */
    private function registrationsByInstitution(): Collection
    {
        return $this->registrants()
            ->selectRaw("coalesce(nullif(institution, ''), 'Not stated') as label")
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when payment_status in ('verified','waived') then 1 else 0 end) as paid")
            ->selectRaw('sum(case when institution_id is null then 1 else 0 end) as custom_entries')
            ->groupBy('label')
            ->orderByDesc('total')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'paid' => (int) $row->paid,
                // Typed in via "Other" rather than chosen from the list, so the
                // spelling may vary between registrants.
                'is_custom' => (int) $row->custom_entries > 0,
            ])
            ->values();
    }

    /** Abstracts attributed to the submitting author's country. */
    private function abstractsByCountry(): Collection
    {
        return AbstractSubmission::query()
            ->join('users', 'users.id', '=', 'abstract_submissions.user_id')
            ->selectRaw("coalesce(nullif(users.country, ''), 'Not stated') as label")
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when abstract_submissions.presentation_type = 'oral' then 1 else 0 end) as oral")
            ->selectRaw("sum(case when abstract_submissions.presentation_type = 'poster' then 1 else 0 end) as poster")
            ->selectRaw("sum(case when abstract_submissions.status = 'accepted' then 1 else 0 end) as accepted")
            ->groupBy('label')
            ->orderByDesc('total')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'oral' => (int) $row->oral,
                'poster' => (int) $row->poster,
                'accepted' => (int) $row->accepted,
            ])
            ->values();
    }
}
