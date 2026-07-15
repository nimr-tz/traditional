<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmed;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_east_african_student_category_sets_the_billing_configuration(): void
    {
        Storage::fake('local');
        FeeCategory::create(['key' => 'student_east_africa', 'label' => 'East Africa Students', 'amount' => 50000, 'currency' => 'TZS', 'active' => true]);

        Mail::fake();

        $response = $this->post('/register', [
            'salutation' => 'Dr.',
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'email' => 'amina@example.com',
            'institution_id' => 'other',
            'institution_other' => 'University of Dodoma',
            'phone' => '+255700000000',
            'participant_type' => 'student',
            'country' => 'Tanzania',
            'fee_category' => 'student_east_africa',
            'student_document' => UploadedFile::fake()->create('student-id.pdf', 100, 'application/pdf'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'amina@example.com')->firstOrFail();

        $this->assertSame('student_east_africa', $user->fee_category);
        $this->assertSame('student', $user->participant_type);
        $this->assertEquals(50000, $user->fee_amount);
        $this->assertTrue((bool) $user->is_east_africa);
        $this->assertSame('pending', $user->payment_status);

        Mail::assertQueued(RegistrationConfirmed::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_selected_non_east_african_participant_category_sets_the_billing_configuration(): void
    {
        // No dedicated practitioner tier in the official fee table — practitioners
        // pay the same rate as any other non-student participant in their region.
        FeeCategory::create(['key' => 'participant_non_east_africa', 'label' => 'Non-East African Participants', 'amount' => 150, 'currency' => 'USD', 'active' => true]);

        Mail::fake();

        $this->post('/register', [
            'salutation' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'institution_id' => 'other',
            'institution_other' => 'Healers Guild',
            'phone' => '+1 555 0100',
            'participant_type' => 'practitioner',
            'country' => 'United States',
            'fee_category' => 'participant_non_east_africa',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'john@example.com')->firstOrFail();

        $this->assertSame('participant_non_east_africa', $user->fee_category);
        $this->assertSame('practitioner', $user->participant_type);
        $this->assertEquals(150, $user->fee_amount);
        $this->assertSame('USD', $user->currency);
        $this->assertFalse((bool) $user->is_east_africa);
    }

    public function test_registration_rejects_a_category_that_is_not_active(): void
    {
        FeeCategory::create([
            'key' => 'student_non_east_africa',
            'label' => 'Non-East African Students',
            'amount' => 50,
            'currency' => 'USD',
            'active' => false,
        ]);

        $response = $this->post('/register', [
            'salutation' => 'Ms.',
            'first_name' => 'Inactive',
            'last_name' => 'Category',
            'email' => 'inactive@example.com',
            'institution_id' => 'other',
            'institution_other' => 'Test Institution',
            'phone' => '+255700000000',
            'participant_type' => 'student',
            'country' => 'Tanzania',
            'fee_category' => 'student_non_east_africa',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('fee_category');
        $this->assertDatabaseMissing('users', ['email' => 'inactive@example.com']);
    }
}
