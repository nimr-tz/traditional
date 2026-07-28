<?php

namespace Tests\Feature\Settings;

use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedFeeCategories(): void
    {
        FeeCategory::insert([
            ['key' => 'participant_east_africa', 'label' => 'East African Participants', 'amount' => 150000, 'currency' => 'TZS', 'active' => true, 'sort_order' => 1],
            ['key' => 'participant_non_east_africa', 'label' => 'Non-East African Participants', 'amount' => 150, 'currency' => 'USD', 'active' => true, 'sort_order' => 2],
            ['key' => 'student_east_africa', 'label' => 'East African Students', 'amount' => 50000, 'currency' => 'TZS', 'active' => true, 'sort_order' => 3],
            ['key' => 'student_non_east_africa', 'label' => 'Non-East African Students', 'amount' => 50, 'currency' => 'USD', 'active' => true, 'sort_order' => 4],
        ]);
    }

    public function test_a_pending_registrant_can_correct_their_registration_category(): void
    {
        $this->seedFeeCategories();

        $user = User::factory()->create([
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'payment_status' => 'pending',
            'country' => 'United Kingdom',
        ]);

        $response = $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'researcher',
            'fee_category' => 'participant_non_east_africa',
        ]);

        $response->assertRedirect();
        $user->refresh();

        $this->assertSame('researcher', $user->participant_type);
        $this->assertSame('participant_non_east_africa', $user->fee_category);
        $this->assertSame('150.00', $user->fee_amount);
        $this->assertSame('USD', $user->currency);
        $this->assertNull($user->student_verification_status);
    }

    public function test_switching_into_a_student_category_starts_a_new_verification(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->seedFeeCategories();

        $user = User::factory()->create([
            'participant_type' => 'researcher',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'pending',
            'student_verification_status' => null,
            'country' => 'Tanzania',
        ]);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
            'student_document' => UploadedFile::fake()->create('student-id.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('pending', $user->student_verification_status);
        $this->assertNotNull($user->student_document_path);
    }

    public function test_switching_into_a_student_category_without_a_document_is_rejected(): void
    {
        $this->seedFeeCategories();

        $user = User::factory()->create([
            'participant_type' => 'researcher',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'pending',
            'country' => 'Tanzania',
        ]);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
        ])->assertSessionHasErrors('student_document');

        $user->refresh();
        $this->assertSame('participant_east_africa', $user->fee_category);
        $this->assertNull($user->student_verification_status);
    }

    public function test_mismatched_participant_type_and_category_are_rejected(): void
    {
        $this->seedFeeCategories();

        $user = User::factory()->create(['payment_status' => 'pending', 'country' => 'Tanzania']);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'researcher',
            'fee_category' => 'student_east_africa',
        ])->assertSessionHasErrors('fee_category');
    }

    public function test_a_registrant_cannot_switch_to_a_cheaper_region_than_their_country_allows(): void
    {
        $this->seedFeeCategories();

        $user = User::factory()->create([
            'participant_type' => 'researcher',
            'fee_category' => 'participant_non_east_africa',
            'fee_amount' => 150,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'country' => 'Germany',
        ]);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'researcher',
            'fee_category' => 'participant_east_africa',
        ])->assertSessionHasErrors('fee_category');

        $user->refresh();
        $this->assertSame('participant_non_east_africa', $user->fee_category);
        $this->assertEquals(150, $user->fee_amount);
    }

    public function test_category_cannot_be_changed_once_a_control_number_has_been_requested(): void
    {
        $this->seedFeeCategories();

        $user = User::factory()->create([
            'participant_type' => 'researcher',
            'fee_category' => 'participant_east_africa',
            'fee_amount' => 150000,
            'currency' => 'TZS',
            'payment_status' => 'submitted',
            'country' => 'Tanzania',
        ]);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('participant_east_africa', $user->fee_category);
        $this->assertSame('researcher', $user->participant_type);
    }
}
