<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffRegistrantDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategories(): void
    {
        FeeCategory::firstOrCreate(['key' => 'participant_east_africa'], [
            'label' => 'East African Participants',
            'amount' => 150000,
            'currency' => 'TZS',
            'active' => true,
            'is_complimentary' => false,
        ]);

        FeeCategory::firstOrCreate(['key' => 'complimentary_media'], [
            'label' => 'Media',
            'amount' => 0,
            'currency' => 'TZS',
            'active' => true,
            'is_complimentary' => true,
        ]);
    }

    /**
     * Builds one of each state the door cares about.
     *
     * @return array<string, User>
     */
    private function aCastOfEveryone(User $staff): array
    {
        $this->seedCategories();

        $inToday = User::factory()->create([
            'name' => 'Aaa InToday',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-INTODAY0001',
        ]);
        Attendance::create(['user_id' => $inToday->id, 'checked_in_at' => now(), 'checked_in_by' => $staff->id]);

        $returning = User::factory()->create([
            'name' => 'Bbb Returning',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-RETURN00001',
        ]);
        Attendance::create([
            'user_id' => $returning->id,
            'checked_in_at' => now()->subDays(2),
            'checked_in_by' => $staff->id,
        ]);

        $neverAttended = User::factory()->create([
            'name' => 'Ccc NeverCame',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-NEVER000001',
        ]);

        $unpaid = User::factory()->create([
            'name' => 'Ddd Unpaid',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'submitted',
        ]);

        $press = User::factory()->create([
            'name' => 'Eee Press',
            'fee_category' => 'complimentary_media',
            'payment_status' => 'waived',
            'registration_code' => 'TMSC-PRESS000001',
        ]);

        return compact('inToday', 'returning', 'neverAttended', 'unpaid', 'press');
    }

    public function test_the_directory_lists_everyone_with_their_standing(): void
    {
        $staff = User::factory()->staff()->create();
        $this->aCastOfEveryone($staff);

        $this->actingAs($staff)
            ->get(route('staff.registrants'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/registrants')
                ->where('people.total', 5)
                ->where('counts.all', 5)
                ->where('counts.here_today', 1)
                ->where('counts.not_arrived', 3)
                ->where('counts.never_attended', 2)
                ->where('counts.unpaid', 1)
                ->where('counts.complimentary', 1)
                // Ordered by name so the register is predictable to work through.
                ->where('people.data.0.name', 'Aaa InToday')
                ->etc());
    }

    public function test_each_filter_narrows_to_the_right_people(): void
    {
        $staff = User::factory()->staff()->create();
        $this->aCastOfEveryone($staff);

        $expected = [
            'here_today' => ['Aaa InToday'],
            'unpaid' => ['Ddd Unpaid'],
            'complimentary' => ['Eee Press'],
            'never_attended' => ['Ccc NeverCame', 'Eee Press'],
            'not_arrived' => ['Bbb Returning', 'Ccc NeverCame', 'Eee Press'],
        ];

        foreach ($expected as $status => $names) {
            $this->actingAs($staff)
                ->get(route('staff.registrants', ['status' => $status]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('people.total', count($names))
                    ->where('filters.status', $status)
                    ->etc());
        }
    }

    public function test_it_can_be_filtered_to_one_fee_category(): void
    {
        $staff = User::factory()->staff()->create();
        $this->aCastOfEveryone($staff);

        $this->actingAs($staff)
            ->get(route('staff.registrants', ['category' => 'complimentary_media']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Eee Press')
                ->etc());
    }

    public function test_search_covers_phone_badge_and_control_number(): void
    {
        $staff = User::factory()->staff()->create();
        $this->seedCategories();

        User::factory()->create([
            'name' => 'Findable Person',
            'phone' => '+255 712 999 888',
            'payment_status' => 'submitted',
            'control_number' => '995910079999',
        ]);

        foreach (['712 999 888', '995910079999', 'Findable'] as $term) {
            $this->actingAs($staff)
                ->get(route('staff.registrants', ['search' => $term]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('people.total', 1)
                    ->where('people.data.0.name', 'Findable Person')
                    ->etc());
        }
    }

    public function test_organisers_are_not_listed_as_attendees(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Zed Staffer']);
        User::factory()->admin()->create(['name' => 'Zed Admin']);
        User::factory()->finance()->create(['name' => 'Zed Finance']);
        User::factory()->create(['name' => 'Zed Registrant']);

        $this->actingAs($staff)
            ->get(route('staff.registrants'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Zed Registrant')
                ->etc());
    }

    public function test_the_directory_is_closed_to_registrants_and_reviewers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.registrants'))
            ->assertForbidden();

        $this->actingAs(User::factory()->reviewer()->create())
            ->get(route('staff.registrants'))
            ->assertForbidden();
    }

    public function test_finance_and_admins_can_also_browse_it(): void
    {
        foreach ([User::factory()->finance(), User::factory()->admin(), User::factory()->superAdmin()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('staff.registrants'))
                ->assertOk();
        }
    }
}
