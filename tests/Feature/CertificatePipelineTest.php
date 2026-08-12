<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\ConferenceSetting;
use App\Models\User;
use App\Support\CertificateWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CertificatePipelineTest extends TestCase
{
    use RefreshDatabase;

    private function attendee(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'name' => 'Asha Nyerere',
            'institution' => 'NIMR',
            'phone' => '+255 712 000 111',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-CERT00000A',
        ], $attributes));

        Attendance::create(['user_id' => $user->id, 'checked_in_at' => now()]);

        return $user;
    }

    private function released(): void
    {
        ConferenceSetting::set('certificate_release_at', now()->subHour()->format('Y-m-d H:i'));
    }

    private function notReleasedYet(): void
    {
        ConferenceSetting::set('certificate_release_at', now()->addDay()->format('Y-m-d H:i'));
    }

    // ── The release gate ────────────────────────────────────────────────────

    /**
     * Without this an attendee scanned in on the first morning could download a
     * certificate for a conference that had not happened.
     */
    public function test_certificates_are_withheld_until_the_release_time(): void
    {
        $this->notReleasedYet();
        $attendee = $this->attendee();

        $this->assertFalse(CertificateWindow::isOpen());

        $this->actingAs($attendee)
            ->get(route('certificate.download'))
            ->assertRedirect(route('certificate.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, Certificate::count());
    }

    public function test_certificates_open_once_the_release_time_passes(): void
    {
        $this->released();
        $attendee = $this->attendee();

        $response = $this->actingAs($attendee)->get(route('certificate.download'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertSame(1, Certificate::where('user_id', $attendee->id)->count());
    }

    public function test_the_release_time_defaults_to_the_closing_day_afternoon(): void
    {
        ConferenceSetting::set('certificate_release_at', null);
        ConferenceSetting::set('end_date', '2026-08-28');

        $this->travelTo('2026-08-28 13:59:00');
        $this->assertFalse(CertificateWindow::isOpen());

        $this->travelTo('2026-08-28 14:00:00');
        $this->assertTrue(CertificateWindow::isOpen());
    }

    /** A garbled setting must not hand out certificates early, nor take the feature down. */
    public function test_an_unparseable_release_time_falls_back_to_the_conference_date(): void
    {
        ConferenceSetting::set('certificate_release_at', 'sometime after lunch');
        ConferenceSetting::set('end_date', '2026-08-28');

        $this->travelTo('2026-08-27 09:00:00');
        $this->assertFalse(CertificateWindow::isOpen());
    }

    // ── Who is entitled ─────────────────────────────────────────────────────

    /** The substance of the document: it says somebody was there. */
    public function test_a_registrant_who_never_attended_gets_nothing(): void
    {
        $this->released();
        $absent = User::factory()->create(['payment_status' => 'verified', 'registration_code' => 'TMSC-ABSENT0001']);

        $this->actingAs($absent)
            ->get(route('certificate.download'))
            ->assertRedirect(route('certificate.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, Certificate::count());
    }

    public function test_an_unsettled_registration_gets_nothing(): void
    {
        $this->released();
        $unpaid = $this->attendee(['payment_status' => 'pending']);

        $this->actingAs($unpaid)
            ->get(route('certificate.download'))
            ->assertRedirect(route('certificate.show'));

        $this->assertSame(0, Certificate::count());
    }

    public function test_the_certificate_page_explains_where_they_stand(): void
    {
        $this->notReleasedYet();
        $attendee = $this->attendee();

        $this->actingAs($attendee)
            ->get(route('certificate.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('certificate')
                ->where('window.is_open', false)
                ->where('daysAttended', 1)
                ->where('isPaid', true)
                ->whereNot('blockedReason', null)
                ->etc());
    }

    // ── Claiming without an account ─────────────────────────────────────────

    public function test_a_walk_in_can_claim_with_their_badge_code(): void
    {
        $this->released();
        $walkIn = $this->attendee(['email' => null, 'name' => 'Mzee Salehe Ramadhani', 'registration_code' => 'TMSC-WALKIN0001']);

        $response = $this->post(route('certificate.claim.submit'), [
            'name' => 'Mzee Salehe Ramadhani',
            'proof' => 'TMSC-WALKIN0001',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertSame(1, Certificate::where('user_id', $walkIn->id)->count());
    }

    public function test_a_phone_number_works_as_proof_however_it_is_spaced(): void
    {
        $this->released();
        $this->attendee(['email' => null]);

        $this->post(route('certificate.claim.submit'), [
            'name' => 'Asha Nyerere',
            'proof' => '+255712000111',
        ])->assertOk();
    }

    /** A name alone would let anyone download a stranger's certificate. */
    public function test_a_name_without_a_matching_second_detail_is_refused(): void
    {
        $this->released();
        $this->attendee();

        $this->post(route('certificate.claim.submit'), [
            'name' => 'Asha Nyerere',
            'proof' => 'a guess',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Certificate::count());
    }

    public function test_the_claim_form_will_not_confirm_whether_a_name_attended(): void
    {
        $this->released();
        $this->attendee();

        // Wrong proof for a real attendee, and a name nobody has, must be
        // indistinguishable — otherwise this becomes a way to check who came.
        $realNameWrongProof = $this->post(route('certificate.claim.submit'), ['name' => 'Asha Nyerere', 'proof' => 'wrong'])
            ->assertRedirect();
        $unknownName = $this->post(route('certificate.claim.submit'), ['name' => 'Nobody At All', 'proof' => 'wrong'])
            ->assertRedirect();

        $this->assertSame(
            session()->get('error'),
            $unknownName->getSession()->get('error'),
        );
        $this->assertNotNull($realNameWrongProof->getSession()->get('error'));
    }

    public function test_claiming_respects_the_release_time_and_attendance(): void
    {
        $this->notReleasedYet();
        $this->attendee();

        $this->post(route('certificate.claim.submit'), [
            'name' => 'Asha Nyerere',
            'proof' => 'TMSC-CERT00000A',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Certificate::count());
    }

    public function test_the_claim_page_is_reachable_without_signing_in(): void
    {
        $this->get(route('certificate.claim'))->assertOk()->assertSee('Get your certificate');
    }

    public function test_a_certificate_can_be_verified_publicly(): void
    {
        $this->released();
        $attendee = $this->attendee();

        $this->actingAs($attendee)->get(route('certificate.download'))->assertOk();

        $code = Certificate::firstOrFail()->certificate_code;

        $this->get(route('certificates.verify', $code))->assertOk();
    }
}
