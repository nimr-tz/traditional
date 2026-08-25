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

    /**
     * Production installs still hold a `registration_deadline` row until the
     * migration clears it, and organizers can re-add the key by hand. Neither
     * may shut the door: the date cutoff is gone, not merely unset.
     */
    public function test_a_leftover_deadline_setting_no_longer_closes_registration(): void
    {
        Mail::fake();
        $this->seedFeeCategory();
        ConferenceSetting::set('registration_deadline', now()->subYear()->toDateString());

        $this->get('/register')->assertStatus(200);
        $this->post('/register', $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'late@example.com']);
    }

    public function test_organizers_can_close_registration_immediately(): void
    {
        Mail::fake();
        $this->seedFeeCategory();
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
                'registration_closed' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('1', ConferenceSetting::get('registration_closed'));
    }
}
