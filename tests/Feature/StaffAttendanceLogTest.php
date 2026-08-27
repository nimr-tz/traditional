<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAttendanceLogTest extends TestCase
{
    use RefreshDatabase;

    private function scan(User $person, User $staff, string $at): Attendance
    {
        return Attendance::create([
            'user_id' => $person->id,
            'checked_in_at' => $at,
            'checked_in_by' => $staff->id,
        ]);
    }

    public function test_it_lists_todays_scans_newest_first(): void
    {
        $staff = User::factory()->staff()->create();
        $early = User::factory()->create(['name' => 'Aaa Early']);
        $late = User::factory()->create(['name' => 'Bbb Late']);

        $this->scan($early, $staff, today()->setTime(8, 15)->toDateTimeString());
        $this->scan($late, $staff, today()->setTime(9, 40)->toDateTimeString());
        // Yesterday — must not show under the default (today) view.
        $this->scan(User::factory()->create(['name' => 'Ccc Yesterday']), $staff, today()->subDay()->setTime(10, 0)->toDateTimeString());

        $this->actingAs($staff)
            ->get(route('staff.attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/attendance')
                ->where('filters.date', today()->toDateString())
                ->where('scans.total', 2)
                ->where('scans.data.0.name', 'Bbb Late')
                ->where('scans.data.1.name', 'Aaa Early')
                ->where('summary.scans', 2));
    }

    public function test_it_can_switch_to_a_past_day(): void
    {
        $staff = User::factory()->staff()->create();
        $this->scan(User::factory()->create(['name' => 'Today Person']), $staff, today()->setTime(9, 0)->toDateTimeString());
        $this->scan(User::factory()->create(['name' => 'Tuesday Person']), $staff, today()->subDays(2)->setTime(9, 0)->toDateTimeString());

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['date' => today()->subDays(2)->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scans.total', 1)
                ->where('scans.data.0.name', 'Tuesday Person'));
    }

    public function test_all_days_shows_every_scan(): void
    {
        $staff = User::factory()->staff()->create();
        $this->scan(User::factory()->create(), $staff, today()->setTime(9, 0)->toDateTimeString());
        $this->scan(User::factory()->create(), $staff, today()->subDay()->setTime(9, 0)->toDateTimeString());
        $this->scan(User::factory()->create(), $staff, today()->subDays(3)->setTime(9, 0)->toDateTimeString());

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['date' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.date', 'all')
                ->where('scans.total', 3)
                ->where('days', fn ($days) => count($days) === 3));
    }

    public function test_search_matches_name_institution_and_badge_code(): void
    {
        $staff = User::factory()->staff()->create();
        $target = User::factory()->create([
            'name' => 'Grace Findable',
            'institution' => 'Kilimanjaro CMC',
            'registration_code' => 'TMSC-ATT0000001',
        ]);
        $this->scan($target, $staff, today()->setTime(9, 0)->toDateTimeString());
        $this->scan(User::factory()->create(['name' => 'Someone Else']), $staff, today()->setTime(9, 5)->toDateTimeString());

        foreach (['Findable', 'Kilimanjaro', 'ATT0000001'] as $term) {
            $this->actingAs($staff)
                ->get(route('staff.attendance', ['search' => $term]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('scans.total', 1)
                    ->where('scans.data.0.name', 'Grace Findable'));
        }
    }

    public function test_it_shows_who_recorded_each_scan(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Door Steward', 'salutation' => 'Mr.']);
        $this->scan(User::factory()->create(['name' => 'Scanned Guest']), $staff, today()->setTime(9, 0)->toDateTimeString());

        $this->actingAs($staff)
            ->get(route('staff.attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('scans.data.0.recorded_by', 'Mr. Door Steward'));
    }

    public function test_the_summary_counts_people_and_the_conference_total(): void
    {
        $staff = User::factory()->staff()->create();
        $returning = User::factory()->create();
        // One person on two days, one person on one day.
        $this->scan($returning, $staff, today()->setTime(9, 0)->toDateTimeString());
        $this->scan($returning, $staff, today()->subDay()->setTime(9, 0)->toDateTimeString());
        $this->scan(User::factory()->create(), $staff, today()->setTime(9, 30)->toDateTimeString());

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['date' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.scans', 3)
                ->where('summary.people', 2)
                ->where('summary.conference_total', 2));

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['date' => today()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.scans', 2)
                ->where('summary.people', 2));
    }

    public function test_it_is_closed_to_registrants_and_reviewers_and_open_to_the_desk(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.attendance'))->assertForbidden();
        $this->actingAs(User::factory()->reviewer()->create())->get(route('staff.attendance'))->assertForbidden();

        foreach ([User::factory()->staff(), User::factory()->finance(), User::factory()->admin()] as $factory) {
            $this->actingAs($factory->create())->get(route('staff.attendance'))->assertOk();
        }
    }
}
