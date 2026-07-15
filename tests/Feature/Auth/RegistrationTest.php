<?php

namespace Tests\Feature\Auth;

use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        FeeCategory::create(['key' => 'participant_east_africa', 'label' => 'East African Participants', 'amount' => 150000, 'currency' => 'TZS', 'active' => true, 'sort_order' => 1]);
        FeeCategory::create(['key' => 'participant_non_east_africa', 'label' => 'Non-East African Participants', 'amount' => 150, 'currency' => 'USD', 'active' => true, 'sort_order' => 2]);
        FeeCategory::create(['key' => 'student_east_africa', 'label' => 'East African Students', 'amount' => 50000, 'currency' => 'TZS', 'active' => true, 'sort_order' => 3]);
        FeeCategory::create(['key' => 'student_non_east_africa', 'label' => 'Non-East African Students', 'amount' => 50, 'currency' => 'USD', 'active' => true, 'sort_order' => 4]);

        $response = $this->get('/register');

        $response->assertStatus(200)->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->has('participantTypes', 7)
            ->where('participantTypes.researcher', 'Researcher')
            ->where('participantTypes.media', 'Media')
            ->has('feeCategories', 4)
            ->where('feeCategories.0.key', 'participant_east_africa')
            ->where('feeCategories.3.key', 'student_non_east_africa')
        );
    }

    public function test_all_standard_registration_fields_except_middle_name_are_required()
    {
        $response = $this->post('/register', [
            'middle_name' => '',
        ]);

        $response->assertSessionHasErrors([
            'salutation',
            'first_name',
            'last_name',
            'email',
            'institution_id',
            'phone',
            'participant_type',
            'country',
            'fee_category',
            'password',
        ]);
        $response->assertSessionDoesntHaveErrors('middle_name');
    }

    public function test_new_users_can_register_by_picking_a_listed_institution()
    {
        Mail::fake();

        FeeCategory::create(['key' => 'participant_east_africa', 'label' => 'East Africa Participants', 'amount' => 150000, 'currency' => 'TZS', 'active' => true]);
        $institution = Institution::create(['name' => 'NIMR', 'active' => true, 'sort_order' => 1]);

        $response = $this->post('/register', [
            'salutation' => 'Dr.',
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'institution_id' => (string) $institution->id,
            'institution_other' => '',
            'phone' => '+255700000000',
            'participant_type' => 'researcher',
            'country' => 'Tanzania',
            'fee_category' => 'participant_east_africa',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $response->assertSessionHas('status', 'verification-link-sent');

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame($institution->id, $user->institution_id);
        $this->assertSame('NIMR', $user->institution);
        $this->assertSame('Test', $user->first_name);
        $this->assertSame('Middle', $user->middle_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('Test Middle User', $user->name);
    }

    public function test_new_users_can_register_with_a_custom_institution_via_other()
    {
        Mail::fake();

        FeeCategory::create(['key' => 'participant_east_africa', 'label' => 'East Africa Participants', 'amount' => 150000, 'currency' => 'TZS', 'active' => true]);

        $response = $this->post('/register', [
            'salutation' => 'Dr.',
            'first_name' => 'Other Institute',
            'last_name' => 'User',
            'email' => 'other-institute@example.com',
            'institution_id' => 'other',
            'institution_other' => 'Village Health Clinic',
            'phone' => '+255700000000',
            'participant_type' => 'practitioner',
            'country' => 'Tanzania',
            'fee_category' => 'participant_east_africa',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $response->assertSessionHas('status', 'verification-link-sent');

        $user = User::where('email', 'other-institute@example.com')->firstOrFail();
        $this->assertNull($user->institution_id);
        $this->assertSame('Village Health Clinic', $user->institution);
    }

    public function test_registering_sends_a_verification_email_and_gates_the_dashboard()
    {
        Mail::fake();
        Notification::fake();

        FeeCategory::create(['key' => 'participant_east_africa', 'label' => 'East Africa Participants', 'amount' => 150000, 'currency' => 'TZS', 'active' => true]);
        $institution = Institution::create(['name' => 'NIMR', 'active' => true, 'sort_order' => 1]);

        $this->post('/register', [
            'salutation' => 'Dr.',
            'first_name' => 'Unverified',
            'last_name' => 'User',
            'email' => 'unverified@example.com',
            'institution_id' => (string) $institution->id,
            'institution_other' => '',
            'phone' => '+255700000000',
            'participant_type' => 'academic',
            'country' => 'Tanzania',
            'fee_category' => 'participant_east_africa',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'unverified@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }
}
