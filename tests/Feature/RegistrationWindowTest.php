<?php

namespace Tests\Feature;

use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationWindowTest extends TestCase
{
    use RefreshDatabase;

    private function seedFeeCategory(): void
    {
        FeeCategory::create([
            'key' => 'participant_east_africa',
            'label' => 'East African Participants',
            'amount' => 150000,
            'currency' => 'TZS',
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'salutation' => 'Dr.',
            'first_name' => 'Late',
            'last_name' => 'Comer',
            'email' => 'late@example.com',
            'institution_id' => 'other',
            'institution_other' => 'NIMR',
            'phone' => '+255700000000',
            'participant_type' => 'researcher',
            'country' => 'Tanzania',
            'fee_category' => 'participant_east_africa',
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ];
    }

    public function test_registration_is_open_by_default(): void
    {
        $this->seedFeeCategory();

        $this->get('/register')->assertStatus(200);
    }

    public function test_registration_closes_after_the_deadline(): void
    {
        Mail::fake();
        $this->seedFeeCategory();
        ConferenceSetting::set('registration_deadline', now()->subDay()->toDateString());

        $this->get('/register')
            ->assertStatus(403)
            ->assertInertia(fn ($page) => $page->component('auth/registration-closed'));

        $this->post('/register', $this->payload())->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => 'late@example.com']);
    }

    public function test_registration_stays_open_on_the_deadline_day(): void
    {
        Mail::fake();
        $this->seedFeeCategory();
        ConferenceSetting::set('registration_deadline', now()->toDateString());

        $this->get('/register')->assertStatus(200);
        $this->post('/register', $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'late@example.com']);
    }

    /** Conference settings are free-text rows; existing installs hold dates like "6 August 2026". */
    public function test_a_human_entered_deadline_is_still_honoured(): void
    {
        Mail::fake();
        $this->seedFeeCategory();
        ConferenceSetting::set('registration_deadline', now()->subWeek()->format('j F Y'));

        $this->get('/register')->assertStatus(403);
        $this->post('/register', $this->payload())->assertRedirect(route('register'));
    }

    /** A garbled setting must not take the public register page down. */
    public function test_an_unparseable_deadline_leaves_registration_open(): void
    {
        $this->seedFeeCategory();
        ConferenceSetting::set('registration_deadline', 'not a date at all');

        $this->get('/register')->assertStatus(200);
    }

    public function test_organizers_can_close_registration_immediately_regardless_of_the_deadline(): void
    {
        Mail::fake();
        $this->seedFeeCategory();
        ConferenceSetting::set('registration_deadline', now()->addYear()->toDateString());
        ConferenceSetting::set('registration_closed', '1');

        $this->get('/register')->assertStatus(403);
        $this->post('/register', $this->payload())->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => 'late@example.com']);
    }

    /** A closed door for new sign-ups must not lock out people who already registered. */
    public function test_existing_registrants_can_still_log_in_when_registration_is_closed(): void
    {
        ConferenceSetting::set('registration_closed', '1');

        $user = User::factory()->create(['email' => 'already@example.com']);

        $this->get('/login')->assertStatus(200);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_can_set_the_registration_window(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['role' => User::ROLE_SUPER_ADMIN])->save();
        $superAdmin->roleAssignments()->firstOrCreate(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.conference.update'), [
                'registration_deadline' => '2026-08-21',
                'registration_closed' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('2026-08-21', ConferenceSetting::get('registration_deadline'));
        $this->assertSame('1', ConferenceSetting::get('registration_closed'));
    }
}
