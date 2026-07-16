<?php

namespace Tests\Feature\Settings;

use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seedFeeCategories();

        $user = User::factory()->create([
            'participant_type' => 'researcher',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'pending',
            'student_verification_status' => null,
        ]);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
        ])->assertRedirect();

        $this->assertSame('pending', $user->refresh()->student_verification_status);
    }

    public function test_mismatched_participant_type_and_category_are_rejected(): void
    {
        $this->seedFeeCategories();

        $user = User::factory()->create(['payment_status' => 'pending']);

        $this->actingAs($user)->patch('/settings/registration', [
            'participant_type' => 'researcher',
            'fee_category' => 'student_east_africa',
        ])->assertSessionHasErrors('fee_category');
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
