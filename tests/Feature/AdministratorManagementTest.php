<?php

namespace Tests\Feature;

use App\Models\AdministratorAccessChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_search_verified_non_administrator_accounts(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $candidate = User::factory()->create([
            'name' => 'Amina Mushi',
            'email' => 'amina@example.com',
        ]);
        User::factory()->unverified()->create([
            'name' => 'Amina Unverified',
            'email' => 'unverified@example.com',
        ]);
        User::factory()->create([
            'name' => 'Amina Administrator',
            'email' => 'another-admin@example.com',
            'is_admin' => true,
        ]);

        $response = $this->actingAs($administrator)->getJson(route('admin.settings.administrators.search', [
            'query' => 'Amina',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $candidate->id)
            ->assertJsonPath('users.0.email', 'amina@example.com');
    }

    public function test_an_administrator_can_grant_access_and_the_change_is_audited(): void
    {
        $administrator = User::factory()->create([
            'name' => 'First Administrator',
            'email' => 'first-admin@example.com',
            'is_admin' => true,
        ]);
        $candidate = User::factory()->create([
            'name' => 'New Administrator',
            'email' => 'new-admin@example.com',
        ]);

        $this->actingAs($administrator)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Administrator access granted.');

        $this->assertTrue($candidate->fresh()->is_admin);
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $candidate->id,
            'target_name' => 'New Administrator',
            'changed_by' => $administrator->id,
            'changed_by_name' => 'First Administrator',
            'action' => 'granted',
        ]);
    }

    public function test_an_unverified_account_cannot_be_granted_administrator_access(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $candidate = User::factory()->unverified()->create();

        $this->actingAs($administrator)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id])
            ->assertSessionHasErrors('user_id');

        $this->assertFalse($candidate->fresh()->is_admin);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_an_administrator_can_remove_another_administrator_and_the_change_is_audited(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Retained Administrator',
            'is_admin' => true,
        ]);
        $removedAdministrator = User::factory()->create([
            'name' => 'Removed Administrator',
            'is_admin' => true,
        ]);

        $this->actingAs($administrator)
            ->delete(route('admin.settings.administrators.destroy', $removedAdministrator))
            ->assertRedirect()
            ->assertSessionHas('success', 'Administrator access removed.');

        $this->assertFalse($removedAdministrator->fresh()->is_admin);
        $this->assertTrue($administrator->fresh()->is_admin);
        $this->assertDatabaseHas('administrator_access_changes', [
            'target_user_id' => $removedAdministrator->id,
            'changed_by' => $administrator->id,
            'action' => 'revoked',
        ]);
    }

    public function test_an_administrator_cannot_remove_their_own_access(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => true]);

        $this->actingAs($administrator)
            ->delete(route('admin.settings.administrators.destroy', $administrator))
            ->assertSessionHasErrors('administrator');

        $this->assertTrue($administrator->fresh()->is_admin);
        $this->assertSame(0, AdministratorAccessChange::count());
    }

    public function test_a_non_administrator_cannot_manage_administrator_access(): void
    {
        $user = User::factory()->create();
        $candidate = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.settings.administrators.store'), ['user_id' => $candidate->id])
            ->assertForbidden();

        $this->assertFalse($candidate->fresh()->is_admin);
    }
}
