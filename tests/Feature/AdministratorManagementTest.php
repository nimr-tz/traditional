<?php

namespace Tests\Feature;

use App\Models\AdministratorAccessChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_browse_every_user_regardless_of_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $participant = User::factory()->create(['name' => 'Amina Mushi', 'email' => 'amina@example.com']);
        $reviewer = User::factory()->reviewer()->create(['name' => 'Amina Reviewer', 'email' => 'reviewer@example.com']);

        $response = $this->actingAs($superAdmin)->getJson(route('admin.users.search', ['query' => 'Amina']));

        $response->assertOk()->assertJsonCount(2, 'users.data');
        $ids = collect($response->json('users.data'))->pluck('id')->all();
        $this->assertContains($participant->id, $ids);
        $this->assertContains($reviewer->id, $ids);
    }

    public function test_a_super_admin_can_filter_users_by_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->create();
        User::factory()->reviewer()->create();

        $response = $this->actingAs($superAdmin)->getJson(route('admin.users.search', ['role' => 'reviewer']));

        $response->assertOk()->assertJsonCount(1, 'users.data')->assertJsonPath('users.data.0.role', 'reviewer');
    }

    /**
     * The console locks the row of a lone super admin so it can't be demoted.
     * That count has to come from the whole table: when it was derived from the
     * search results instead, searching for one super admin by name returned a
     * result set of one and locked a row that was perfectly safe to change.
     */
    public function test_super_admin_count_covers_every_user_not_just_the_search_results(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['name' => 'First Administrator']);
        User::factory()->superAdmin()->create(['name' => 'Second Administrator']);
        User::factory()->superAdmin()->create(['name' => 'Third Administrator']);

        $response = $this->actingAs($superAdmin)->getJson(route('admin.users.search', ['query' => 'Second']));

        $response->assertOk()
            ->assertJsonCount(1, 'users.data')
            ->assertJsonPath('super_admin_count', 3);
    }

    public function test_a_super_admin_can_be_demoted_while_another_super_admin_remains(): void
    {
        $acting = User::factory()->superAdmin()->create(['name' => 'First Administrator']);
        $target = User::factory()->superAdmin()->create(['name' => 'Second Administrator']);

        $response = $this->actingAs($acting)->patch(route('admin.users.update-roles', $target), [
            'roles' => ['user'],
            'primary_role' => 'user',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->isSuperAdmin());
        $this->assertTrue($acting->fresh()->isSuperAdmin());
    }

    public function test_a_super_admin_can_change_any_users_role_directly(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['name' => 'First Administrator']);
        $reviewer = User::factory()->reviewer()->create(['name' => 'Existing Reviewer']);

        $this->actingAs($superAdmin)
            ->patch(route('admin.users.update-roles', $reviewer), ['roles' => ['admin'], 'primary_role' => 'admin'])
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
            ->patch(route('admin.users.update-roles', $participant), [
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
            ->patch(route('admin.users.update-roles', $reviewer), ['roles' => ['user'], 'primary_role' => 'user'])
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
            ->patch(route('admin.users.update-roles', $candidate), ['roles' => ['reviewer'], 'primary_role' => 'reviewer'])
            ->assertSessionHasErrors('roles');

        $this->assertSame('user', $candidate->fresh()->role);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_super_admin_cannot_change_their_own_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->patch(route('admin.users.update-roles', $superAdmin), ['roles' => ['admin'], 'primary_role' => 'admin'])
            ->assertSessionHasErrors('roles');

        $this->assertSame('super_admin', $superAdmin->fresh()->role);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_plain_admin_cannot_manage_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $candidate = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update-roles', $candidate), ['roles' => ['reviewer'], 'primary_role' => 'reviewer'])
            ->assertForbidden();

        $this->assertSame('user', $candidate->fresh()->role);
    }

    public function test_a_non_administrator_cannot_manage_roles(): void
    {
        $user = User::factory()->create();
        $candidate = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.users.update-roles', $candidate), ['roles' => ['reviewer'], 'primary_role' => 'reviewer'])
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

    public function test_role_management_has_its_own_page_and_is_super_admin_only(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('roleAccessChanges')
            );

        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($admin)->getJson(route('admin.users.search'))->assertForbidden();
    }

    /** Conference settings is about the conference, not about who can do what. */
    public function test_conference_settings_no_longer_carries_role_management(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/index')
                ->has('conferenceSettings')
                ->has('feeCategories')
                ->missing('roleAccessChanges')
            );
    }

    public function test_a_plain_admin_can_still_assign_reviewers_and_verify_students(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.registrations.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.abstracts.index'))->assertOk();
    }
}
