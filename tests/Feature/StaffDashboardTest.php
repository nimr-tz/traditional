<?php

namespace Tests\Feature;

use App\Mail\FeeWaived;
use App\Mail\PaymentConfirmed;
use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\User;
use App\Services\BadgePrinter;
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

    /**
     * What a search result opens onto: everything about the person and every
     * action the desk can take, in one place, rather than crammed into the
     * search row.
     */
    public function test_the_registrant_page_shows_full_detail_and_history(): void
    {
        $staff = User::factory()->staff()->create();
        $registrant = $this->paidRegistrant(['name' => 'Full Detail Person', 'institution' => 'NIMR']);
        Attendance::create(['user_id' => $registrant->id, 'checked_in_at' => now(), 'checked_in_by' => $staff->id]);

        $this->actingAs($staff)
            ->get(route('staff.registrant', $registrant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/registrant')
                ->where('person.id', $registrant->id)
                ->where('person.name', 'Full Detail Person')
                ->where('person.institution', 'NIMR')
                ->where('person.days_attended', 1)
                ->has('attendance', 1)
                ->etc());
    }

    /** Organisers are not registrants — nothing about them is a desk's business. */
    public function test_the_registrant_page_404s_for_a_non_registrant(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($staff)
            ->get(route('staff.registrant', $admin))
            ->assertNotFound();
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
                'institution_id' => 'other',
                'institution_other' => 'NIMR',
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
                'institution_id' => 'other',
                'institution_other' => 'Village Clinic',
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

    public function test_name_institution_and_participant_type_are_required_but_phone_is_not(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
            ])
            ->assertSessionHasErrors(['name', 'institution_id', 'participant_type'])
            ->assertSessionDoesntHaveErrors('phone');
    }

    /**
     * A leader will not give a desk clerk a personal mobile, and plenty of
     * attendees have no email either. Neither is required — the desk reads the
     * control number off its own screen.
     */
    public function test_a_walk_in_needs_neither_a_phone_nor_an_email(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'No Contact Person',
                'institution_id' => 'other',
                'institution_other' => 'Ministry of Health',
                'participant_type' => 'policy_maker',
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $walkIn = User::where('name', 'No Contact Person')->firstOrFail();

        $this->assertNull($walkIn->phone);
        $this->assertNull($walkIn->email);
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

    /**
     * A complimentary walk-in owes nothing and never receives a control number,
     * so the desk must be handed the badge itself — not a billing panel about a
     * number that will never exist.
     */
    public function test_a_complimentary_walk_in_is_handed_a_badge_and_no_control_number(): void
    {
        $this->seedComplimentaryCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Press Person',
                'phone' => '+255 700 000 222',
                'institution_id' => 'other',
                'institution_other' => 'The Citizen',
                'participant_type' => 'media',
                'fee_category' => 'complimentary_media',
            ])
            ->assertRedirect();

        $walkIn = User::where('name', 'Press Person')->firstOrFail();
        $flash = session('walkIn');

        $this->assertNull($flash['control_number']);
        $this->assertNotNull($flash['badge'], 'A comped walk-in is paid, so their badge exists immediately.');
        $this->assertSame($walkIn->registration_code, $flash['badge']['registrationCode']);
        // Positioned from the same config as the PDF, so the two cannot drift.
        $this->assertSame(config('badge.placeholders'), $flash['badge']['placeholders']);
    }

    /**
     * The artwork has slots for a name, an institution and a QR, and nothing
     * else, so no fee category reaches the badge. The print log still records
     * the real one: it is a record of who was issued a badge rather than of what
     * the face showed.
     */
    public function test_no_fee_category_reaches_the_badge_but_the_print_log_keeps_it(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();
        $paid = $this->paidRegistrant([
            'name' => 'Ordinary Attendee',
            'fee_category' => 'participant_east_africa',
        ]);

        $badge = app(BadgePrinter::class)->preview($paid);

        $this->assertArrayNotHasKey('categoryLabel', $badge);
        $this->assertSame('Ordinary Attendee', $badge['name']);

        $this->actingAs($staff)->get(route('staff.badge', $paid))->assertOk();

        $this->assertSame(
            'East African Participants',
            $paid->badgePrints()->latest('printed_at')->first()->printed_category,
        );
    }

    /**
     * A leader is registered at the desk as a walk-in with their role in the
     * "Position / role" field. It rides onto the badge in the institution slot —
     * "DIRECTOR GENERAL, MUHAS" — while the name line stays the salutation plus
     * the name.
     */
    public function test_a_dignitary_walk_in_carries_their_position_onto_the_badge(): void
    {
        $this->seedComplimentaryCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'salutation' => 'Hon.',
                'position_title' => 'Director General',
                'name' => 'Jane Mwakalinga',
                'phone' => '+255 700 000 900',
                'institution_id' => 'other',
                'institution_other' => 'MUHAS',
                'participant_type' => 'policy_maker',
                'fee_category' => 'complimentary_media',
            ])
            ->assertRedirect();

        $leader = User::where('name', 'Jane Mwakalinga')->firstOrFail();
        $this->assertSame('Director General', $leader->position_title);
        $this->assertSame('Director General, MUHAS', $leader->badge_affiliation);

        $badge = app(BadgePrinter::class)->preview($leader);
        $this->assertSame('Hon. Jane Mwakalinga', $badge['name']);
        $this->assertSame('Director General, MUHAS', $badge['institution']);

        // What actually printed is what the log keeps.
        $this->actingAs($staff)->get(route('staff.badge', $leader))->assertOk();
        $this->assertSame(
            'Director General, MUHAS',
            $leader->badgePrints()->latest('printed_at')->first()->printed_institution,
        );
    }

    /** No position on file means the affiliation line is exactly the institution, unchanged. */
    public function test_an_ordinary_registrant_badge_is_untouched_by_the_position_field(): void
    {
        $this->seedParticipantCategory();

        $paid = $this->paidRegistrant([
            'name' => 'Plain Attendee',
            'institution' => 'NIMR',
            'fee_category' => 'participant_east_africa',
            'position_title' => null,
        ]);

        $this->assertSame('NIMR', $paid->badge_affiliation);
        $this->assertSame('NIMR', app(BadgePrinter::class)->preview($paid)['institution']);
    }

    /**
     * A name mistyped at the desk is fixed in place — no second registration,
     * which would leave a duplicate in every count.
     */
    public function test_the_desk_can_correct_a_registrant_after_registration(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();
        $person = $this->paidRegistrant([
            'name' => 'Jhon Mistpye',
            'salutation' => 'Mr.',
            'institution' => 'MUHUS',
            'fee_category' => 'participant_east_africa',
        ]);

        $this->actingAs($staff)
            ->patch(route('staff.registrant.update', $person), [
                'salutation' => 'Prof.',
                'name' => 'John Mtapaya',
                'position_title' => 'Director General',
                'institution' => 'MUHAS',
                'phone' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $person->refresh();
        $this->assertSame('John Mtapaya', $person->name);
        $this->assertSame('Prof.', $person->salutation);
        $this->assertSame('Director General', $person->position_title);
        $this->assertSame('MUHAS', $person->institution);
        $this->assertNull($person->phone);
        $this->assertSame('Director General, MUHAS', $person->badge_affiliation);
    }

    /** Retyping the institution frees it from a stale link to a canonical row. */
    public function test_correcting_the_institution_clears_a_stale_institution_id(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();
        $institution = Institution::create(['name' => 'Old Institute', 'active' => true, 'sort_order' => 1]);
        $person = $this->paidRegistrant([
            'name' => 'Linked Person',
            'institution' => 'Old Institute',
            'institution_id' => $institution->id,
            'fee_category' => 'participant_east_africa',
        ]);

        $this->actingAs($staff)
            ->patch(route('staff.registrant.update', $person), [
                'name' => 'Linked Person',
                'institution' => 'New Institute',
            ])
            ->assertRedirect();

        $person->refresh();
        $this->assertSame('New Institute', $person->institution);
        $this->assertNull($person->institution_id);
    }

    /** The print log records what was on the card, so a later correction does not rewrite it. */
    public function test_a_correction_does_not_touch_an_already_printed_badge_log(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();
        $person = $this->paidRegistrant([
            'name' => 'Printed Already',
            'institution' => 'NIMR',
            'fee_category' => 'participant_east_africa',
        ]);

        $this->actingAs($staff)->get(route('staff.badge', $person))->assertOk();

        $this->actingAs($staff)
            ->patch(route('staff.registrant.update', $person), ['name' => 'Corrected Afterwards', 'institution' => 'NIMR'])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'reprint'));

        $this->assertSame('Printed Already', $person->badgePrints()->latest('printed_at')->first()->printed_name);
    }

    public function test_a_correction_still_needs_a_name(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();
        $person = $this->paidRegistrant(['name' => 'Has A Name', 'fee_category' => 'participant_east_africa']);

        $this->actingAs($staff)
            ->patch(route('staff.registrant.update', $person), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('Has A Name', $person->fresh()->name);
    }

    public function test_correcting_details_is_closed_to_registrants_and_only_applies_to_them(): void
    {
        $this->seedParticipantCategory();
        $person = $this->paidRegistrant(['name' => 'Target', 'fee_category' => 'participant_east_africa']);

        // A plain registrant cannot reach the desk tools at all.
        $this->actingAs(User::factory()->create())
            ->patch(route('staff.registrant.update', $person), ['name' => 'Hacked'])
            ->assertForbidden();

        // Staff can, but only for registrants — not other staff or admins.
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->patch(route('staff.registrant.update', User::factory()->admin()->create()), ['name' => 'Nope'])
            ->assertNotFound();

        $this->assertSame('Target', $person->fresh()->name);
    }

    /** The coordinates are measured off the artwork, so a swap must not go unnoticed. */
    public function test_the_badge_ships_the_real_artwork_at_its_own_proportions(): void
    {
        $this->assertFileExists(public_path(config('badge.template.background')));

        [$width, $height] = getimagesize(public_path(config('badge.template.background')));

        $this->assertEqualsWithDelta(
            config('badge.template.width_mm') / config('badge.template.height_mm'),
            $width / $height,
            0.005,
            'The artwork would be stretched: its aspect ratio no longer matches width_mm/height_mm.',
        );
    }

    /** Showing a badge on screen is not printing one. */
    public function test_registering_a_walk_in_does_not_count_as_printing_their_badge(): void
    {
        $this->seedComplimentaryCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('staff.walk-ins.store'), [
            'name' => 'Unprinted Guest',
            'phone' => '+255 700 000 333',
            'institution_id' => 'other',
            'institution_other' => 'NIMR',
            'participant_type' => 'media',
            'fee_category' => 'complimentary_media',
        ]);

        $walkIn = User::where('name', 'Unprinted Guest')->firstOrFail();

        $this->assertSame(0, $walkIn->badgePrints()->count());

        // And neither is opening their record, or the desk's first real print
        // would announce itself as a reprint.
        $this->actingAs($staff)->get(route('staff.registrant', $walkIn))->assertOk();

        $this->assertSame(0, $walkIn->badgePrints()->count());
    }

    /** Someone who still owes gets the number to pay with, and no badge. */
    public function test_a_paying_walk_in_is_handed_a_control_number_and_no_badge(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Owes Money',
                'phone' => '+255 700 000 444',
                'institution_id' => 'other',
                'institution_other' => 'NIMR',
                'participant_type' => 'researcher',
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
            ])
            ->assertRedirect();

        $flash = session('walkIn');

        $this->assertNull($flash['badge'], 'Nobody unpaid may be shown a badge.');
    }

    /** The detail page carries the badge for anyone who has one. */
    public function test_the_registrant_page_shows_a_badge_only_once_they_have_paid(): void
    {
        $staff = User::factory()->staff()->create();
        $unpaid = User::factory()->create(['payment_status' => 'submitted', 'registration_code' => null]);
        $paid = $this->paidRegistrant(['name' => 'Settled Person']);

        $this->actingAs($staff)
            ->get(route('staff.registrant', $unpaid))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('staff/registrant')->where('badge', null)->etc());

        $this->actingAs($staff)
            ->get(route('staff.registrant', $paid))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/registrant')
                ->where('badge.registrationCode', $paid->registration_code)
                ->etc());
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

    private function seedStudentCategory(): FeeCategory
    {
        return FeeCategory::firstOrCreate(['key' => 'student_east_africa'], [
            'label' => 'East African Students',
            'amount' => 50000,
            'currency' => 'TZS',
            'active' => true,
        ]);
    }

    /**
     * There is no document upload at the desk, so a walk-in student can only
     * ever be verified by staff eyeballing their ID in person and ticking the
     * box — this is the one path a walk-in student can pay through at all.
     */
    public function test_a_student_walk_in_verified_in_person_can_be_billed_immediately(): void
    {
        Mail::fake();
        $this->seedStudentCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Verified Student',
                'phone' => '+255 700 000 444',
                'institution_id' => 'other',
                'institution_other' => 'University of Dar es Salaam',
                'participant_type' => 'student',
                'country' => 'Tanzania',
                'fee_category' => 'student_east_africa',
                'student_verified_in_person' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student = User::where('name', 'Verified Student')->firstOrFail();

        $this->assertSame('verified', $student->student_verification_status);
        $this->assertSame($staff->id, $student->student_verified_by);
        $this->assertNotNull($student->student_verified_at);
        // Sync queue connection in testing runs the sandbox assignment job inline.
        $this->assertNotNull($student->control_number);
    }

    /** Without the checkbox, a walk-in student registers but stays blocked until they verify online — unchanged from before. */
    public function test_a_student_walk_in_not_verified_in_person_is_still_blocked_from_billing(): void
    {
        $this->seedStudentCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Unverified Student',
                'phone' => '+255 700 000 555',
                'institution_id' => 'other',
                'institution_other' => 'University of Dar es Salaam',
                'participant_type' => 'student',
                'country' => 'Tanzania',
                'fee_category' => 'student_east_africa',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student = User::where('name', 'Unverified Student')->firstOrFail();

        $this->assertNull($student->student_verification_status);
        $this->assertNull($student->control_number);
    }

    /**
     * Country only ever picks a fee tier, and a complimentary category has no
     * tier — so a free walk-in should not be blocked on a country nobody asked
     * for a reason to collect.
     */
    public function test_country_is_not_required_for_a_complimentary_walk_in(): void
    {
        Mail::fake();
        $this->seedComplimentaryCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'No Country Guest',
                'phone' => '+255 700 000 666',
                'institution_id' => 'other',
                'institution_other' => 'Wire Service',
                'participant_type' => 'media',
                'fee_category' => 'complimentary_media',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guest = User::where('name', 'No Country Guest')->firstOrFail();
        $this->assertTrue($guest->isPaid());
    }

    /**
     * The dedicated free-entry categories cover people who never pay. For
     * everyone else — an invited guest who this time isn't paying — finance
     * can waive the fee at registration instead of billing then forgiving it.
     */
    public function test_finance_can_waive_a_fee_at_registration(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Invited Guest',
                'email' => 'invited.guest@example.com',
                'phone' => '+255 700 000 777',
                'institution_id' => 'other',
                'institution_other' => "Minister's Office",
                'participant_type' => 'decision_maker',
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
                'waive' => true,
                'waive_notes' => 'Invited guest — not paying.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guest = User::where('name', 'Invited Guest')->firstOrFail();

        $this->assertSame('waived', $guest->payment_status);
        $this->assertSame($finance->id, $guest->payment_verified_by);
        $this->assertSame('Invited guest — not paying.', $guest->payment_notes);
        $this->assertNotNull($guest->registration_code);
        $this->assertNull($guest->control_number);
        Mail::assertQueued(FeeWaived::class);
    }

    /** Waiving is finance's decision alone, same as settling any other payment. */
    public function test_staff_cannot_waive_a_fee_at_registration(): void
    {
        $this->seedParticipantCategory();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.walk-ins.store'), [
                'name' => 'Sneaky Waiver',
                'phone' => '+255 700 000 888',
                'institution_id' => 'other',
                'institution_other' => 'NIMR',
                'participant_type' => 'researcher',
                'country' => 'Tanzania',
                'fee_category' => 'participant_east_africa',
                'waive' => true,
                'waive_notes' => 'Trying my luck.',
            ])
            ->assertSessionHasErrors('waive');

        $this->assertDatabaseMissing('users', ['name' => 'Sneaky Waiver']);
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
                'institution_id' => 'other',
                'institution_other' => 'Daily News',
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
                'institution_id' => 'other',
                'institution_other' => 'Reuters',
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

    /** The secretariat is conference staff, not an attendee category — it must not appear as something to register someone into. */
    public function test_secretariat_is_not_offered_as_a_desk_category(): void
    {
        FeeCategory::firstOrCreate(['key' => 'complimentary_secretariat'], [
            'label' => 'Secretariat',
            'amount' => 0,
            'currency' => 'TZS',
            'active' => false,
            'is_complimentary' => true,
        ]);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deskOptions.fee_categories', fn ($categories) => $categories->doesntContain(
                    fn ($category) => $category['key'] === 'complimentary_secretariat',
                ))
                ->etc());
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
