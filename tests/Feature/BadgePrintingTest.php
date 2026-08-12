<?php

namespace Tests\Feature;

use App\Models\BadgePrintLog;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgePrintingTest extends TestCase
{
    use RefreshDatabase;

    private function paidRegistrant(array $attributes = []): User
    {
        FeeCategory::firstOrCreate(['key' => 'participant_east_africa'], [
            'label' => 'East African Participants',
            'amount' => 150000,
            'currency' => 'TZS',
            'active' => true,
        ]);

        return User::factory()->create(array_merge([
            'name' => 'Asha Nyerere',
            'institution' => 'NIMR',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-BADGE00001',
        ], $attributes));
    }

    public function test_a_paid_registrant_can_download_their_own_badge(): void
    {
        $user = $this->paidRegistrant();

        $response = $this->actingAs($user)->get(route('badge.download'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    /** The rule the whole system rests on: no payment, no badge. */
    public function test_an_unpaid_registrant_is_sent_back_to_the_payment_page(): void
    {
        $user = User::factory()->create(['payment_status' => 'pending', 'registration_code' => null]);

        $this->actingAs($user)
            ->get(route('badge.download'))
            ->assertRedirect(route('payment.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, BadgePrintLog::count());
    }

    public function test_staff_can_print_a_badge_at_the_desk(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->paidRegistrant();

        $this->actingAs($staff)
            ->get(route('staff.badge', $attendee))
            ->assertOk();

        $log = BadgePrintLog::firstOrFail();

        $this->assertSame($attendee->id, $log->user_id);
        $this->assertSame($staff->id, $log->printed_by);
        $this->assertSame(1, $log->print_number);
        $this->assertFalse($log->isReprint());
        $this->assertSame('TMSC-BADGE00001', $log->registration_code);
    }

    public function test_staff_cannot_print_a_badge_for_someone_who_has_not_paid(): void
    {
        $staff = User::factory()->staff()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending', 'registration_code' => null]);

        $this->actingAs($staff)
            ->get(route('staff.badge', $unpaid))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, BadgePrintLog::count());
    }

    /**
     * Reprints are allowed on purpose — badges get lost — but each one is
     * counted, so a reissue is visible rather than a quiet duplicate.
     */
    public function test_each_reprint_is_counted(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->paidRegistrant();

        foreach (range(1, 3) as $expected) {
            $this->actingAs($staff)->get(route('staff.badge', $attendee))->assertOk();

            $this->assertSame($expected, BadgePrintLog::where('user_id', $attendee->id)->max('print_number'));
        }

        $this->assertSame(3, $attendee->badgePrints()->count());
        $this->assertTrue(BadgePrintLog::where('print_number', 2)->firstOrFail()->isReprint());
    }

    /** The desk needs the count before it prints, to warn about a reissue. */
    public function test_the_desk_reports_how_many_badges_a_person_already_has(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->paidRegistrant(['name' => 'Reprint Candidate']);

        $this->actingAs($staff)->get(route('staff.badge', $attendee))->assertOk();

        $this->actingAs($staff)
            ->get(route('staff.dashboard', ['search' => 'Reprint']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('results.0.badges_printed', 1)
                ->where('results.0.can_print_badge', true)
                ->etc());
    }

    /**
     * A complimentary badge has to say *how* the wearer qualifies, so a steward
     * can see why someone with no paid registration is entitled to be there.
     */
    public function test_a_complimentary_badge_records_the_category_that_admitted_them(): void
    {
        FeeCategory::create([
            'key' => 'complimentary_media',
            'label' => 'Media',
            'amount' => 0,
            'currency' => 'TZS',
            'active' => true,
            'is_complimentary' => true,
        ]);

        $staff = User::factory()->staff()->create();
        $press = User::factory()->create([
            'name' => 'Press Photographer',
            'institution' => 'The Citizen',
            'fee_category' => 'complimentary_media',
            'payment_status' => 'waived',
            'registration_code' => 'TMSC-PRESS00001',
        ]);

        $this->actingAs($staff)->get(route('staff.badge', $press))->assertOk();

        $this->assertSame('Media', BadgePrintLog::firstOrFail()->printed_category);
    }

    /**
     * Accounts settled before the code was minted on payment were refused a
     * badge over a bookkeeping gap. They get one, and the code is minted then.
     */
    public function test_a_paid_registrant_missing_a_code_is_given_one_rather_than_refused(): void
    {
        $legacy = $this->paidRegistrant(['registration_code' => null]);

        $this->assertTrue($legacy->canPrintBadge());

        $this->actingAs($legacy)->get(route('badge.download'))->assertOk();

        $legacy->refresh();

        $this->assertNotNull($legacy->registration_code);
        $this->assertSame($legacy->registration_code, BadgePrintLog::firstOrFail()->registration_code);
    }

    /** Minting on demand must not become a way in for someone who has not paid. */
    public function test_an_unpaid_registrant_is_still_never_given_a_code(): void
    {
        $staff = User::factory()->staff()->create();
        $unpaid = User::factory()->create(['payment_status' => 'pending', 'registration_code' => null]);

        $this->actingAs($staff)->get(route('staff.badge', $unpaid))->assertRedirect();

        $this->assertNull($unpaid->fresh()->registration_code);
        $this->assertSame(0, BadgePrintLog::count());
    }

    /** What was on the card is the fact worth keeping, even if the account changes later. */
    public function test_the_log_keeps_what_was_printed_not_what_the_account_says_now(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->paidRegistrant(['name' => 'Original Name']);

        $this->actingAs($staff)->get(route('staff.badge', $attendee))->assertOk();

        $attendee->update(['name' => 'Corrected Name Afterwards']);

        $this->assertSame('Original Name', BadgePrintLog::firstOrFail()->printed_name);
    }
}
