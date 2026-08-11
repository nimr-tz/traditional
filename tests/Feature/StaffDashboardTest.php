<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function paidRegistrant(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-'.strtoupper(fake()->bothify('??????????')),
        ], $attributes));
    }

    public function test_staff_land_on_the_checkin_console_rather_than_the_registrant_dashboard(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'password123']);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password123'])
            ->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_the_console_counts_expected_arrivals_and_who_has_come_through(): void
    {
        $staff = User::factory()->staff()->create();

        $arrived = $this->paidRegistrant(['name' => 'Asha Nyerere']);
        $this->paidRegistrant(['name' => 'Neema Waiting']);
        // Waived registrants hold a badge too, so they are expected.
        $waived = $this->paidRegistrant(['name' => 'Guest Speaker', 'payment_status' => 'waived']);
        // Unpaid registrants have no badge and are not expected at the door,
        // but they are still registered and must be listed.
        User::factory()->create(['name' => 'Unpaid Person', 'payment_status' => 'pending']);

        Attendance::create([
            'user_id' => $arrived->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/dashboard')
                ->where('stats.registered', 4)
                ->where('stats.expected', 3)
                ->where('stats.checked_in', 1)
                ->where('stats.today', 1)
                ->where('stats.not_arrived', 2)
                ->where('stats.unpaid', 1)
                ->where('stats.recorded_by_me', 1)
                ->where('recent.0.name', 'Asha Nyerere')
                ->where('recent.0.recorded_by', $staff->name)
                ->etc());

        $this->assertSame('waived', $waived->fresh()->payment_status);
    }

    /** The whole point: the desk sees everyone, with each person's standing. */
    public function test_every_registrant_is_listed_with_their_standing(): void
    {
        $staff = User::factory()->staff()->create();

        $arrived = $this->paidRegistrant(['name' => 'Aaa Arrived']);
        $this->paidRegistrant(['name' => 'Bbb Expected']);
        User::factory()->create(['name' => 'Ccc Unpaid', 'payment_status' => 'pending']);

        Attendance::create([
            'user_id' => $arrived->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 3)
                // Ordered by name, so the list is predictable to scan.
                ->where('people.data.0.name', 'Aaa Arrived')
                ->whereNot('people.data.0.checked_in_at', null)
                ->where('people.data.1.name', 'Bbb Expected')
                ->where('people.data.1.is_paid', true)
                ->where('people.data.1.checked_in_at', null)
                ->where('people.data.2.name', 'Ccc Unpaid')
                ->where('people.data.2.is_paid', false)
                ->etc());
    }

    public function test_the_list_can_be_narrowed_to_who_is_still_expected(): void
    {
        $staff = User::factory()->staff()->create();

        $arrived = $this->paidRegistrant(['name' => 'Already In']);
        $this->paidRegistrant(['name' => 'Still Coming']);
        User::factory()->create(['name' => 'Owes Money', 'payment_status' => 'pending']);

        Attendance::create([
            'user_id' => $arrived->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['status' => 'not_arrived']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Still Coming')
                ->etc());

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['status' => 'arrived']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Already In')
                ->etc());

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['status' => 'unpaid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Owes Money')
                ->etc());
    }

    public function test_search_finds_people_by_name_email_or_badge_code(): void
    {
        $staff = User::factory()->staff()->create();

        $this->paidRegistrant(['name' => 'Findable Person', 'registration_code' => 'TMSC-FINDME1234']);
        User::factory()->create(['name' => 'Owing Person', 'payment_status' => 'pending']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => 'TMSC-FINDME1234']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Findable Person')
                ->etc());

        // Someone who still owes must be findable, with the reason visible.
        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => 'Owing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.is_paid', false)
                ->where('people.data.0.checked_in_at', null)
                ->etc());
    }

    public function test_organisers_are_not_listed_as_people_coming_through_the_door(): void
    {
        $staff = User::factory()->staff()->create();
        User::factory()->admin()->create();
        User::factory()->finance()->create();
        $this->paidRegistrant(['name' => 'A Real Registrant']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'A Real Registrant')
                ->etc());
    }

    public function test_the_console_is_closed_to_registrants_and_reviewers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.dashboard'))
            ->assertForbidden();

        $this->actingAs(User::factory()->reviewer()->create())
            ->get(route('staff.dashboard'))
            ->assertForbidden();
    }

    public function test_admins_can_also_see_the_console(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('staff.dashboard'))
            ->assertOk();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('staff.dashboard'))
            ->assertOk();
    }
}
