<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_two_roles_can_reach_routes_for_either_role_regardless_of_which_is_active(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->reviewer()->create();
        $user->roleAssignments()->create(['role' => 'finance']);

        $this->actingAs($superAdmin)
            ->patch(route('admin.settings.users.update-roles', $user), [
                'roles' => ['reviewer', 'finance'],
                'primary_role' => 'reviewer',
            ]);

        $this->assertEqualsCanonicalizing(['reviewer', 'finance'], $user->fresh()->roles());

        // Cosmetic switch: access to both roles' routes never depends on which is "active".
        $this->actingAs($user)->get(route('admin.abstracts.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.finance.dashboard'))->assertOk();

        $this->actingAs($user)
            ->patch(route('active-role.update'), ['role' => 'finance'])
            ->assertRedirect(route('admin.finance.dashboard'));

        $this->assertSame('finance', $user->fresh()->active_role);

        // Still reachable after switching the active/displayed role away from it.
        $this->actingAs($user)->get(route('admin.abstracts.index'))->assertOk();
    }

    public function test_switching_to_a_role_the_user_does_not_hold_is_rejected(): void
    {
        $user = User::factory()->reviewer()->create();

        $this->actingAs($user)
            ->patch(route('active-role.update'), ['role' => 'super_admin'])
            ->assertSessionHasErrors('role');

        $this->assertNull($user->fresh()->active_role);
    }

    public function test_login_redirects_to_the_users_last_active_role_not_always_their_primary_role(): void
    {
        $user = User::factory()->reviewer()->create();
        $user->roleAssignments()->create(['role' => 'finance']);
        $user->forceFill(['active_role' => 'finance'])->save();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.finance.dashboard', absolute: false));
    }

    public function test_a_registrant_stays_visible_in_registrant_lists_after_gaining_a_second_role(): void
    {
        $admin = User::factory()->admin()->create();
        $participant = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.settings.users.update-roles', $participant), [
                'roles' => ['user', 'reviewer'],
                'primary_role' => 'reviewer',
            ])
            ->assertForbidden(); // admin (not super_admin) can't manage roles — grant via the model instead.

        $participant->roleAssignments()->create(['role' => 'reviewer']);
        $participant->forceFill(['role' => 'reviewer'])->save();

        $this->assertSame('reviewer', $participant->fresh()->role);

        $this->actingAs($admin)
            ->get(route('admin.registrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/registrations/index')
                ->where('counts.total', 1));
    }
}
