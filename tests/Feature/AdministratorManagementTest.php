<?php

namespace Tests\Feature;

use App\Models\AdministratorAccessChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_search_verified_plain_user_accounts(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $candidate = User::factory()->create([
            'name' => 'Amina Mushi',
            'email' => 'amina@example.com',
        ]);
        User::factory()->unverified()->create([
            'name' => 'Amina Unverified',
            'email' => 'unverified@example.com',
        ]);
        User::factory()->reviewer()->create([
            'name' => 'Amina Reviewer',
            'email' => 'reviewer@example.com',
        ]);

        $response = $this->actingAs($superAdmin)->getJson(route('admin.settings.administrators.search', [
            'query' => 'Amina',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $candidate->id)
            ->assertJsonPath('users.0.email', 'amina@example.com');
    }

    public function test_a_super_admin_can_grant_a_role_and_the_change_is_audited(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'First Administrator',
            'email' => 'first-admin@example.com',
        ]);
        $candidate = User::factory()->create([
            'name' => 'New Reviewer',
            'email' => 'new-reviewer@example.com',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id, 'role' => 'reviewer'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Role granted.');

        $this->assertSame('reviewer', $candidate->fresh()->role);
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $candidate->id,
            'target_name' => 'New Reviewer',
            'changed_by' => $superAdmin->id,
            'changed_by_name' => 'First Administrator',
            'action' => 'granted',
            'role' => 'reviewer',
        ]);
    }

    public function test_an_unverified_account_cannot_be_granted_a_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $candidate = User::factory()->unverified()->create();

        $this->actingAs($superAdmin)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id, 'role' => 'reviewer'])
            ->assertSessionHasErrors('user_id');

        $this->assertSame('user', $candidate->fresh()->role);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_super_admin_can_remove_another_users_role_and_the_change_is_audited(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Retained Administrator',
        ]);
        $reviewer = User::factory()->reviewer()->create([
            'name' => 'Removed Reviewer',
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('admin.settings.administrators.destroy', $reviewer))
            ->assertRedirect()
            ->assertSessionHas('success', 'Role removed.');

        $this->assertSame('user', $reviewer->fresh()->role);
        $this->assertSame('super_admin', $superAdmin->fresh()->role);
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $reviewer->id,
            'changed_by' => $superAdmin->id,
            'action' => 'revoked',
            'role' => 'reviewer',
        ]);
    }

    public function test_a_super_admin_cannot_remove_their_own_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->delete(route('admin.settings.administrators.destroy', $superAdmin))
            ->assertSessionHasErrors('administrator');

        $this->assertSame('super_admin', $superAdmin->fresh()->role);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_plain_admin_can_also_grant_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $candidate = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id, 'role' => 'reviewer'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Role granted.');

        $this->assertSame('reviewer', $candidate->fresh()->role);
    }

    public function test_a_non_administrator_cannot_manage_roles(): void
    {
        $user = User::factory()->create();
        $candidate = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id, 'role' => 'reviewer'])
            ->assertForbidden();

        $this->assertSame('user', $candidate->fresh()->role);
    }
}
