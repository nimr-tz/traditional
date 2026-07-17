<?php

namespace Tests\Feature;

use App\Models\AdministratorAccessChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_browse_every_user_regardless_of_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $participant = User::factory()->create(['name' => 'Amina Mushi', 'email' => 'amina@example.com']);
        $reviewer = User::factory()->reviewer()->create(['name' => 'Amina Reviewer', 'email' => 'reviewer@example.com']);

        $response = $this->actingAs($superAdmin)->getJson(route('admin.settings.users.index', ['query' => 'Amina']));

        $response->assertOk()->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($participant->id, $ids);
        $this->assertContains($reviewer->id, $ids);
    }

    public function test_a_super_admin_can_filter_users_by_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->create();
        User::factory()->reviewer()->create();

        $response = $this->actingAs($superAdmin)->getJson(route('admin.settings.users.index', ['role' => 'reviewer']));

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'reviewer');
    }

    public function test_a_super_admin_can_change_any_users_role_directly(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['name' => 'First Administrator']);
        $reviewer = User::factory()->reviewer()->create(['name' => 'Existing Reviewer']);

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.users.update-roles', $reviewer), ['roles' => ['admin'], 'primary_role' => 'admin'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $reviewer->fresh();
        $this->assertSame('admin', $fresh->role);
        $this->assertSame(['admin'], $fresh->roles());
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $reviewer->id,
            'changed_by' => $superAdmin->id,
            'action' => 'granted',
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $reviewer->id,
            'changed_by' => $superAdmin->id,
            'action' => 'revoked',
            'role' => 'reviewer',
        ]);
    }

    public function test_a_super_admin_can_grant_a_user_more_than_one_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $participant = User::factory()->create();

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.users.update-roles', $participant), [
                'roles' => ['user', 'reviewer'],
                'primary_role' => 'reviewer',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $participant->fresh();
        $this->assertSame('reviewer', $fresh->role);
        $this->assertEqualsCanonicalizing(['user', 'reviewer'], $fresh->roles());
        $this->assertTrue($fresh->canReviewAbstracts());
        $this->assertTrue($fresh->hasRole('user'));
    }

    public function test_setting_a_users_role_back_to_user_revokes_it_and_is_audited(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $reviewer = User::factory()->reviewer()->create(['name' => 'Removed Reviewer']);

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.users.update-roles', $reviewer), ['roles' => ['user'], 'primary_role' => 'user'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('user', $reviewer->fresh()->role);
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $reviewer->id,
            'changed_by' => $superAdmin->id,
            'action' => 'revoked',
            'role' => 'reviewer',
        ]);
    }

    public function test_an_unverified_account_cannot_be_granted_a_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $candidate = User::factory()->unverified()->create();

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.users.update-roles', $candidate), ['roles' => ['reviewer'], 'primary_role' => 'reviewer'])
            ->assertSessionHasErrors('roles');

        $this->assertSame('user', $candidate->fresh()->role);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_super_admin_cannot_change_their_own_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.users.update-roles', $superAdmin), ['roles' => ['admin'], 'primary_role' => 'admin'])
            ->assertSessionHasErrors('roles');

        $this->assertSame('super_admin', $superAdmin->fresh()->role);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_plain_admin_cannot_manage_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $candidate = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.settings.users.update-roles', $candidate), ['roles' => ['reviewer'], 'primary_role' => 'reviewer'])
            ->assertForbidden();

        $this->assertSame('user', $candidate->fresh()->role);
    }

    public function test_a_non_administrator_cannot_manage_roles(): void
    {
        $user = User::factory()->create();
        $candidate = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.settings.users.update-roles', $candidate), ['roles' => ['reviewer'], 'primary_role' => 'reviewer'])
            ->assertForbidden();

        $this->assertSame('user', $candidate->fresh()->role);
    }

    public function test_only_super_admin_can_reach_conference_settings(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($superAdmin)->get(route('admin.settings.edit'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_a_plain_admin_can_still_assign_reviewers_and_verify_students(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.registrations.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.abstracts.index'))->assertOk();
    }
}
