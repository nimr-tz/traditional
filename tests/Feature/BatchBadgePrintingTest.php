<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BadgePrintLog;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchBadgePrintingTest extends TestCase
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

    private function paid(string $name, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => $name,
            'institution' => 'NIMR',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-'.strtoupper(substr(md5($name), 0, 10)),
        ], $attributes));
    }

    public function test_staff_print_one_pdf_of_badges_for_the_paid_registrants_in_view(): void
    {
        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        $this->paid('Aaa Paid');
        $this->paid('Bbb Paid');
        User::factory()->create(['name' => 'Ccc Unpaid', 'payment_status' => 'submitted', 'registration_code' => null]);

        $response = $this->actingAs($staff)->get(route('staff.registrants.badges'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        // Only the two paid registrants were logged; the unpaid one was never
        // in the run.
        $this->assertSame(2, BadgePrintLog::count());
        $this->assertSame(
            ['Aaa Paid', 'Bbb Paid'],
            BadgePrintLog::orderBy('printed_name')->pluck('printed_name')->all(),
        );
    }

    public function test_the_run_follows_the_category_filter(): void
    {
        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        $this->paid('Delegate One');
        $this->paid('Delegate Two');
        $this->paid('Press Pass', ['fee_category' => 'complimentary_media', 'payment_status' => 'waived']);

        $this->actingAs($staff)
            ->get(route('staff.registrants.badges', ['category' => 'complimentary_media']))
            ->assertOk();

        $this->assertSame(1, BadgePrintLog::count());
        $this->assertSame('Press Pass', BadgePrintLog::firstOrFail()->printed_name);
    }

    public function test_the_run_follows_the_status_filter(): void
    {
        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        $here = $this->paid('Already Here');
        Attendance::create(['user_id' => $here->id, 'checked_in_at' => now(), 'checked_in_by' => $staff->id]);
        $this->paid('Not Arrived Yet');

        $this->actingAs($staff)
            ->get(route('staff.registrants.badges', ['status' => 'not_arrived']))
            ->assertOk();

        $this->assertSame(['Not Arrived Yet'], BadgePrintLog::pluck('printed_name')->all());
    }

    public function test_a_view_with_nobody_paid_returns_no_pdf(): void
    {
        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        User::factory()->create(['name' => 'Still Owes', 'payment_status' => 'submitted', 'registration_code' => null]);

        $this->actingAs($staff)
            ->get(route('staff.registrants.badges', ['status' => 'unpaid']))
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertSame(0, BadgePrintLog::count());
    }

    public function test_a_run_larger_than_the_limit_is_refused(): void
    {
        config(['badge.batch_limit' => 2]);

        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        $this->paid('One Paid');
        $this->paid('Two Paid');
        $this->paid('Three Paid');

        $this->actingAs($staff)
            ->get(route('staff.registrants.badges'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, BadgePrintLog::count());
    }

    public function test_every_badge_in_the_run_is_logged_as_a_print_and_a_second_run_is_a_reprint(): void
    {
        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        $one = $this->paid('Runner One');
        $two = $this->paid('Runner Two');

        $this->actingAs($staff)->get(route('staff.registrants.badges'))->assertOk();
        $this->actingAs($staff)->get(route('staff.registrants.badges'))->assertOk();

        foreach ([$one, $two] as $person) {
            $this->assertSame(2, $person->badgePrints()->count());
            $this->assertSame([1, 2], $person->badgePrints()->orderBy('print_number')->pluck('print_number')->all());
        }

        $this->assertSame($staff->id, BadgePrintLog::firstOrFail()->printed_by);
    }

    public function test_the_register_reports_how_many_badges_the_run_would_print(): void
    {
        $this->seedCategories();
        $staff = User::factory()->staff()->create();

        $this->paid('Paid A');
        $this->paid('Paid B');
        User::factory()->create(['name' => 'Unpaid C', 'payment_status' => 'submitted', 'registration_code' => null]);

        $this->actingAs($staff)
            ->get(route('staff.registrants'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('printableCount', 2)
                ->where('batchLimit', (int) config('badge.batch_limit'))
                ->etc());
    }

    public function test_the_run_is_closed_to_registrants(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.registrants.badges'))
            ->assertForbidden();
    }
}
