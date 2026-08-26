<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CheckinController;
use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One QR, two readers: a phone camera opens the public verification page, the
 * check-in app records the day's attendance.
 */
class BadgeQrCodeTest extends TestCase
{
    use RefreshDatabase;

    private function attendee(array $attributes = []): User
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
            'registration_code' => 'TMSC-QRTEST00001',
        ], $attributes));
    }

    public function test_a_phone_camera_lands_on_a_page_confirming_the_attendee(): void
    {
        $attendee = $this->attendee();

        $this->get(route('badges.verify', $attendee->registration_code))
            ->assertOk()
            ->assertSee('Asha Nyerere')
            ->assertSee('NIMR')
            // The issuer is the whole reason the page is worth anything.
            ->assertSee('National Institute for Medical Research')
            // What somebody paid is nobody's business at a door or in an interview.
            ->assertDontSee('East African Participants');
    }

    /**
     * The page is read years later, sometimes by an employer checking a CV, so
     * it must never confirm attendance on the strength of a registration alone.
     */
    public function test_it_separates_attending_from_merely_registering(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->attendee();

        $this->get(route('badges.verify', $attendee->registration_code))
            ->assertOk()
            ->assertSee('Verified registration')
            ->assertSee('was a registered participant of the')
            ->assertDontSee('attended the');

        Attendance::create([
            'user_id' => $attendee->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
        ]);

        $this->get(route('badges.verify', $attendee->registration_code))
            ->assertOk()
            ->assertSee('Verified participation')
            ->assertSee('attended the');
    }

    /** Public page, so it must show no more than the badge already prints. */
    public function test_the_public_page_leaks_no_contact_or_payment_detail(): void
    {
        $attendee = $this->attendee([
            'email' => 'asha.private@example.com',
            'phone' => '+255 712 000 111',
            'control_number' => '995910070001',
        ]);

        $response = $this->get(route('badges.verify', $attendee->registration_code));

        $response->assertOk()
            ->assertDontSee('asha.private@example.com')
            ->assertDontSee('+255 712 000 111')
            ->assertDontSee('995910070001');
    }

    public function test_it_reports_attendance_without_requiring_a_login(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->attendee();

        Attendance::create([
            'user_id' => $attendee->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
        ]);

        // Stated as a fact about the conference, not as a door readout: the
        // page has to still make sense long after the day it was scanned.
        $this->get(route('badges.verify', $attendee->registration_code))
            ->assertOk()
            ->assertSee('attended the')
            ->assertSee('Present on');
    }

    public function test_an_unknown_or_unsettled_badge_is_refused(): void
    {
        $this->get(route('badges.verify', 'TMSC-DOESNOTEXIST'))
            ->assertOk()
            ->assertSee('Badge not recognised');

        // A registration whose payment was later reset must not keep a live badge.
        $reset = $this->attendee(['payment_status' => 'pending']);

        $this->get(route('badges.verify', $reset->registration_code))
            ->assertOk()
            ->assertSee('Badge not recognised')
            ->assertDontSee('Asha Nyerere');
    }

    /** The app reads the same square, so it has to cope with the URL form. */
    public function test_the_app_records_attendance_from_the_scanned_url(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->attendee();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/checkin/scan', ['code' => route('badges.verify', $attendee->registration_code)])
            ->assertOk()
            ->assertJson(['already_checked_in' => false, 'days_attended' => 1]);

        $this->assertTrue($attendee->fresh()->isCheckedInToday());
    }

    /** Badges printed before the change carry a bare code, and must still work. */
    public function test_the_app_still_accepts_a_bare_code(): void
    {
        $staff = User::factory()->staff()->create();
        $attendee = $this->attendee();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/checkin/scan', ['code' => 'TMSC-QRTEST00001'])
            ->assertOk()
            ->assertJson(['already_checked_in' => false]);
    }

    public function test_the_code_is_extracted_from_every_shape_a_scan_can_take(): void
    {
        $cases = [
            'TMSC-ABC1234567' => 'TMSC-ABC1234567',
            '  TMSC-ABC1234567  ' => 'TMSC-ABC1234567',
            'https://tmsc.apps.nimr.or.tz/badges/verify/TMSC-ABC1234567' => 'TMSC-ABC1234567',
            'https://tmsc.apps.nimr.or.tz/badges/verify/TMSC-ABC1234567/' => 'TMSC-ABC1234567',
            'http://127.0.0.1:8000/badges/verify/TMSC-ABC1234567?utm=x' => 'TMSC-ABC1234567',
        ];

        foreach ($cases as $scanned => $expected) {
            $this->assertSame($expected, CheckinController::registrationCodeFrom($scanned), "failed for: {$scanned}");
        }
    }
}
