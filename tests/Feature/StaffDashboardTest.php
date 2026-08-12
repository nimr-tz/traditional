<?php

namespace Tests\Feature;

use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seedParticipantCategory(): FeeCategory
    {
        return FeeCategory::firstOrCreate(['key' => 'participant_east_africa'], [
            'label' => 'East African Participants',
            'amount' => 150000,
            'currency' => 'TZS',
            'active' => true,
        ]);
    }

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
        // Unpaid registrants have no badge and are not expected at the door.
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
                ->where('stats.expected', 3)
                ->where('stats.here_today', 1)
                ->where('stats.not_arrived_today', 2)
                ->where('stats.attended_ever', 1)
                ->where('canManageFinance', false)
                ->etc());

        $this->assertSame('waived', $waived->fresh()->payment_status);
    }

    /** The page opens on a search, not a roster — no results until you ask for someone. */
    public function test_the_desk_opens_empty_and_only_shows_who_you_search_for(): void
    {
        $staff = User::factory()->staff()->create();
        $this->paidRegistrant(['name' => 'Someone Registered']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('results', null)->etc());

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => 'Someone']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.0.name', 'Someone Registered')
                ->etc());
    }

    public function test_search_covers_badge_and_control_number_and_finds_the_unpaid(): void
    {
        $staff = User::factory()->staff()->create();

        $this->paidRegistrant(['name' => 'Badge Holder', 'registration_code' => 'TMSC-FINDME1234']);
        User::factory()->create([
            'name' => 'Owing Person',
            'payment_status' => 'submitted',
            'control_number' => '995910073640',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => 'TMSC-FINDME1234']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('results.0.name', 'Badge Holder')->etc());

        // The person who still owes is exactly who the desk needs to act on.
        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => '995910073640']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.0.name', 'Owing Person')
                ->where('results.0.is_paid', false)
                ->etc());
    }

    public function test_organisers_are_not_searchable_as_attendees(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Zed Staffer']);
        User::factory()->admin()->create(['name' => 'Zed Admin']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => 'Zed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('results', [])->etc());
    }

    public function test_staff_can_register_a_walk_in_who_lands_unpaid_owing_the_real_fee(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Walk In Person',
                'email' => 'walkin@example.com',
                'phone' => '+255 700 000 000',
                'institution' => 'NIMR',
                'participant_type' => 'researcher',
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
            ])
            ->assertRedirect();

        $walkIn = User::where('email', 'walkin@example.com')->firstOrFail();

        $this->assertSame('participant_east_africa', $walkIn->fee_category);
        $this->assertSame('150000.00', $walkIn->fee_amount);
        $this->assertFalse($walkIn->isPaid());
        // No badge until the money is in.
        $this->assertNull($walkIn->registration_code);
    }

    /**
     * Plenty of attendees reach the desk without an address they can recall.
     * The phone number is what carries their control number.
     */
    public function test_a_walk_in_can_be_registered_without_an_email_address(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'No Email Person',
                'phone' => '+255 700 000 111',
                'institution' => 'Village Clinic',
                'participant_type' => 'practitioner',
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $walkIn = User::where('name', 'No Email Person')->firstOrFail();

        $this->assertNull($walkIn->email);
        $this->assertSame('+255 700 000 111', $walkIn->phone);
        $this->assertSame('participant_east_africa', $walkIn->fee_category);
    }

    public function test_name_phone_and_institution_are_all_required(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
            ])
            ->assertSessionHasErrors(['name', 'phone', 'institution', 'participant_type']);
    }

    /** A missing address must not throw and abandon a confirmed payment halfway. */
    public function test_notifications_skip_the_email_when_there_is_no_address(): void
    {
        Mail::fake();
        $finance = User::factory()->finance()->create();
        $noEmail = User::factory()->create(['email' => null, 'payment_status' => 'pending']);

        $this->actingAs($finance)
            ->post(route('staff.confirm-payment', $noEmail), ['notes' => 'Cash at the desk.'])
            ->assertRedirect();

        $noEmail->refresh();

        $this->assertSame('verified', $noEmail->payment_status);
        $this->assertNotNull($noEmail->registration_code);
        Mail::assertNothingQueued();
    }

    private function seedComplimentaryCategory(): FeeCategory
    {
        return FeeCategory::firstOrCreate(['key' => 'complimentary_media'], [
            'label' => 'Media',
            'amount' => 0,
            'currency' => 'TZS',
            'active' => true,
            'is_complimentary' => true,
        ]);
    }

    /**
     * Media and secretariat attend by role. No bill, no control number, badge
     * straight away — a fee recorded then forgiven would misstate both the
     * revenue and the reason they are there.
     */
    public function test_a_complimentary_walk_in_owes_nothing_and_gets_a_badge_at_once(): void
    {
        Mail::fake();
        $this->seedComplimentaryCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Press Photographer',
                'phone' => '+255 700 000 222',
                'institution' => 'Daily News',
                'participant_type' => 'media',
                'country' => 'Tanzania',
                'fee_category' => 'complimentary_media',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guest = User::where('name', 'Press Photographer')->firstOrFail();

        $this->assertSame('waived', $guest->payment_status);
        $this->assertTrue($guest->isPaid());
        $this->assertNotNull($guest->registration_code);
        // No bill was raised, so there is nothing for them to pay.
        $this->assertNull($guest->control_number);
        $this->assertNull($guest->billing_request_id);
        $this->assertStringContainsString('Complimentary', $guest->payment_notes);
    }

    /**
     * The tier guard exists to stop someone paying an East African rate from
     * abroad. A complimentary category has no region to police, so it must not
     * be blocked by it.
     */
    public function test_a_complimentary_category_ignores_the_regional_tier_rules(): void
    {
        Mail::fake();
        $this->seedComplimentaryCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Foreign Correspondent',
                'phone' => '+44 7700 900999',
                'institution' => 'Reuters',
                'participant_type' => 'media',
                'country' => 'United Kingdom',
                'fee_category' => 'complimentary_media',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('name', 'Foreign Correspondent')->firstOrFail()->isPaid());
    }

    /** Free entry is granted at the desk, never claimed on the public form. */
    public function test_the_public_register_form_refuses_a_complimentary_category(): void
    {
        $this->seedComplimentaryCategory();

        $this->post('/register', [
            'salutation' => 'Mr.',
            'first_name' => 'Chancer',
            'last_name' => 'Freeloader',
            'email' => 'chancer@example.com',
            'institution_id' => 'other',
            'institution_other' => 'Nowhere',
            'phone' => '+255700000333',
            'participant_type' => 'media',
            'country' => 'Tanzania',
            'fee_category' => 'complimentary_media',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ])->assertSessionHasErrors('fee_category');

        $this->assertDatabaseMissing('users', ['email' => 'chancer@example.com']);
    }

    public function test_staff_cannot_settle_a_payment_from_the_desk(): void
    {
        Mail::fake();
        $staff = User::factory()->staff()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($staff)
            ->post(route('staff.confirm-payment', $unpaid))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('staff.waive', $unpaid), ['notes' => 'Invited guest.'])
            ->assertForbidden();

        $unpaid->refresh();

        $this->assertSame('pending', $unpaid->payment_status);
        $this->assertNull($unpaid->registration_code);
    }

    public function test_finance_can_settle_at_the_desk_and_the_badge_follows(): void
    {
        Mail::fake();
        $finance = User::factory()->finance()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($finance)
            ->post(route('staff.confirm-payment', $unpaid), ['notes' => 'Paid cash at the desk.'])
            ->assertRedirect();

        $unpaid->refresh();

        $this->assertSame('verified', $unpaid->payment_status);
        $this->assertSame($finance->id, $unpaid->payment_verified_by);
        $this->assertNotNull($unpaid->registration_code);
        Mail::assertQueued(PaymentConfirmed::class, fn ($mail) => $mail->hasTo($unpaid->email));
    }

    public function test_waiving_at_the_desk_requires_a_reason(): void
    {
        Mail::fake();
        $finance = User::factory()->finance()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($finance)
            ->post(route('staff.waive', $unpaid))
            ->assertSessionHasErrors('notes');

        $this->assertSame('pending', $unpaid->fresh()->payment_status);

        $this->actingAs($finance)
            ->post(route('staff.waive', $unpaid), ['notes' => 'Keynote speaker.'])
            ->assertRedirect();

        $unpaid->refresh();

        $this->assertSame('waived', $unpaid->payment_status);
        $this->assertNotNull($unpaid->registration_code);
        Mail::assertQueued(FeeWaived::class);
    }

    public function test_finance_can_reach_the_desk(): void
    {
        $this->actingAs(User::factory()->finance()->create())
            ->get(route('staff.dashboard'))
            ->assertOk();
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
