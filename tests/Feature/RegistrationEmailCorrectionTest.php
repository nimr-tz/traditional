<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationEmailCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $admin->roleAssignments()->firstOrCreate(['role' => User::ROLE_ADMIN]);

        return $admin;
    }

    public function test_an_admin_can_correct_a_mistyped_email_and_it_is_re_verified(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $registrant = User::factory()->create([
            'email' => 'typo@exampl.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.registrations.email.update', $registrant), [
                'email' => 'correct@example.com',
                'reason' => 'typo reported by phone',
            ])
            ->assertRedirect();

        $registrant->refresh();

        $this->assertSame('correct@example.com', $registrant->email);
        $this->assertNull($registrant->email_verified_at);
        Notification::assertSentTo($registrant, VerifyEmail::class);

        $this->assertDatabaseHas('registration_email_changes', [
            'user_id' => $registrant->id,
            'previous_email' => 'typo@exampl.com',
            'new_email' => 'correct@example.com',
            'changed_by' => $admin->id,
            'changed_by_email' => 'admin@example.com',
            'reason' => 'typo reported by phone',
        ]);
    }

    public function test_the_corrected_address_must_not_already_belong_to_someone_else(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'taken@example.com']);
        $registrant = User::factory()->create(['email' => 'typo@exampl.com']);

        $this->actingAs($this->admin())
            ->patch(route('admin.registrations.email.update', $registrant), ['email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame('typo@exampl.com', $registrant->refresh()->email);
        $this->assertDatabaseCount('registration_email_changes', 0);
    }

    public function test_the_address_must_be_lowercase_and_valid(): void
    {
        $registrant = User::factory()->create(['email' => 'typo@exampl.com']);

        $this->actingAs($this->admin())
            ->patch(route('admin.registrations.email.update', $registrant), ['email' => 'NotAnEmail'])
            ->assertSessionHasErrors('email');
    }

    /** Staff/reviewer/admin accounts aren't conference registrants; this route isn't a general user editor. */
    public function test_only_registrant_accounts_can_be_corrected(): void
    {
        $reviewer = User::factory()->create(['email' => 'reviewer@example.com']);
        $reviewer->forceFill(['role' => User::ROLE_REVIEWER])->save();
        $reviewer->roleAssignments()->delete();
        $reviewer->roleAssignments()->create(['role' => User::ROLE_REVIEWER]);

        $this->actingAs($this->admin())
            ->patch(route('admin.registrations.email.update', $reviewer), ['email' => 'new@example.com'])
            ->assertForbidden();

        $this->assertSame('reviewer@example.com', $reviewer->refresh()->email);
    }

    public function test_a_plain_registrant_cannot_change_anyone_else_email(): void
    {
        $registrant = User::factory()->create(['email' => 'typo@exampl.com']);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->patch(route('admin.registrations.email.update', $registrant), ['email' => 'hijack@example.com'])
            ->assertForbidden();

        $this->assertSame('typo@exampl.com', $registrant->refresh()->email);
    }

    public function test_a_finance_user_cannot_change_a_registrant_email(): void
    {
        $finance = User::factory()->create();
        $finance->forceFill(['role' => User::ROLE_FINANCE])->save();
        $finance->roleAssignments()->delete();
        $finance->roleAssignments()->create(['role' => User::ROLE_FINANCE]);

        $registrant = User::factory()->create(['email' => 'typo@exampl.com']);

        $this->actingAs($finance)
            ->patch(route('admin.registrations.email.update', $registrant), ['email' => 'new@example.com'])
            ->assertForbidden();
    }
}
