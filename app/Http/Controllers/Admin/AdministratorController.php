<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministratorAccessChange;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    /**
     * The Users & Roles console. Deliberately its own page rather than a panel
     * on Conference Settings: who can do what is people administration, not a
     * property of the conference.
     */
    public function index(): Response
    {
        // super_admin only (see routes/admin.php).
        return Inertia::render('admin/users/index', [
            'roleAccessChanges' => AdministratorAccessChange::query()
                ->latest()
                ->limit(10)
                ->get([
                    'id',
                    'target_name',
                    'target_email',
                    'changed_by_name',
                    'action',
                    'role',
                    'created_at',
                ]),
        ]);
    }

    /**
     * Browse/search every user for the role-management panel — unlike the old
     * grant-only flow, this is not scoped to plain 'user' accounts, since a
     * super admin can change anyone's role directly.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(User::ROLES)],
        ]);

        $users = User::query()
            ->with('roleAssignments')
            ->when($data['query'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($data['role'] ?? null, fn ($query, $role) => $query->withRole($role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'roles' => $user->roles(),
                'email_verified' => $user->hasVerifiedEmail(),
            ]);

        return response()->json([
            'users' => $users,
            // Counted across every user, not just this page of results. The UI
            // locks the row of a lone super admin so it can't be demoted, and
            // if it derived that count from the search results, any search
            // narrow enough to return one super admin would lock a row that is
            // perfectly safe to change.
            'super_admin_count' => User::withRole(User::ROLE_SUPER_ADMIN)->count(),
        ]);
    }

    /**
     * Assigns a user's full set of roles at once (not just a single role) —
     * one of the assigned roles is designated "primary", which decides their
     * default nav/home dashboard and is what legacy single-role displays
     * (exports, audit log) show.
     */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $target = $user;

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(User::ROLES)],
            'primary_role' => ['required', Rule::in(User::ROLES)],
        ]);

        $newRoles = array_values(array_unique($data['roles']));
        $primaryRole = $data['primary_role'];

        if (! in_array($primaryRole, $newRoles, true)) {
            throw ValidationException::withMessages([
                'primary_role' => 'The primary role must be one of the assigned roles.',
            ]);
        }

        if ($target->is($request->user())) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot change your own roles. Ask another super admin.',
            ]);
        }

        $oldRoles = $target->roles();

        $sortedOld = $oldRoles;
        sort($sortedOld);
        $sortedNew = $newRoles;
        sort($sortedNew);

        if ($sortedOld === $sortedNew && $primaryRole === $target->role) {
            return back();
        }

        $losingSuperAdmin = in_array(User::ROLE_SUPER_ADMIN, $oldRoles, true)
            && ! in_array(User::ROLE_SUPER_ADMIN, $newRoles, true);

        if ($losingSuperAdmin) {
            $remainingSuperAdmins = User::withRole(User::ROLE_SUPER_ADMIN)
                ->where('id', '!=', $target->id)
                ->count();

            if ($remainingSuperAdmins === 0) {
                throw ValidationException::withMessages([
                    'roles' => 'The final super admin cannot lose the super admin role.',
                ]);
            }
        }

        $grantsElevatedRole = array_diff($newRoles, [User::ROLE_USER]) !== [];

        if ($grantsElevatedRole && ! $target->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'roles' => 'The user must verify their email before receiving a role.',
            ]);
        }

        DB::transaction(function () use ($request, $target, $newRoles, $primaryRole, $oldRoles) {
            $addedRoles = array_diff($newRoles, $oldRoles);
            $removedRoles = array_diff($oldRoles, $newRoles);

            $target->roleAssignments()->whereIn('role', $removedRoles)->delete();
            foreach ($addedRoles as $role) {
                $target->roleAssignments()->firstOrCreate(['role' => $role]);
            }

            $target->forceFill([
                'role' => $primaryRole,
                'active_role' => in_array($target->active_role, $newRoles, true) ? $target->active_role : null,
            ])->save();

            foreach ($addedRoles as $role) {
                AdministratorAccessChange::create([
                    'target_user_id' => $target->id,
                    'target_name' => $target->full_name,
                    'target_email' => $target->email,
                    'changed_by' => $request->user()->id,
                    'changed_by_name' => $request->user()->full_name,
                    'changed_by_email' => $request->user()->email,
                    'action' => 'granted',
                    'role' => $role,
                ]);
            }

            foreach ($removedRoles as $role) {
                AdministratorAccessChange::create([
                    'target_user_id' => $target->id,
                    'target_name' => $target->full_name,
                    'target_email' => $target->email,
                    'changed_by' => $request->user()->id,
                    'changed_by_name' => $request->user()->full_name,
                    'changed_by_email' => $request->user()->email,
                    'action' => 'revoked',
                    'role' => $role,
                ]);
            }
        });

        return back()->with('success', "{$target->full_name}'s roles were updated.");
    }
}
