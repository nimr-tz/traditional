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

class AdministratorController extends Controller
{
    /** Roles that can be granted to a plain user via this panel. */
    private const ASSIGNABLE_ROLES = [
        User::ROLE_REVIEWER,
        User::ROLE_STAFF,
        User::ROLE_ADMIN,
        User::ROLE_SUPER_ADMIN,
    ];

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $users = User::query()
            ->where('role', User::ROLE_USER)
            ->whereNotNull('email_verified_at')
            ->where(function ($query) use ($data) {
                $query->where('name', 'like', "%{$data['query']}%")
                    ->orWhere('email', 'like', "%{$data['query']}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);

        DB::transaction(function () use ($data, $request) {
            $target = User::query()->lockForUpdate()->findOrFail($data['user_id']);

            if ($target->role !== User::ROLE_USER) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user already has an elevated role. Remove it before assigning a new one.',
                ]);
            }

            if (! $target->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'user_id' => 'The user must verify their email before receiving a role.',
                ]);
            }

            $target->forceFill(['role' => $data['role']])->save();
            $this->recordChange($target, $request->user(), 'granted', $data['role']);
        });

        return back()->with('success', 'Role granted.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($target->role === User::ROLE_USER) {
                throw ValidationException::withMessages([
                    'administrator' => 'This user does not have an elevated role.',
                ]);
            }

            if ($target->is($request->user())) {
                throw ValidationException::withMessages([
                    'administrator' => 'You cannot remove your own role. Ask another admin to remove it.',
                ]);
            }

            if ($target->role === User::ROLE_SUPER_ADMIN) {
                $superAdmins = User::query()
                    ->where('role', User::ROLE_SUPER_ADMIN)
                    ->lockForUpdate()
                    ->get(['id']);

                if ($superAdmins->count() <= 1) {
                    throw ValidationException::withMessages([
                        'administrator' => 'The final super admin cannot be removed.',
                    ]);
                }
            }

            $revokedRole = $target->role;
            $target->forceFill(['role' => User::ROLE_USER])->save();
            $this->recordChange($target, $request->user(), 'revoked', $revokedRole);
        });

        return back()->with('success', 'Role removed.');
    }

    private function recordChange(User $target, User $actor, string $action, string $role): void
    {
        AdministratorAccessChange::create([
            'target_user_id' => $target->id,
            'target_name' => $target->name,
            'target_email' => $target->email,
            'changed_by' => $actor->id,
            'changed_by_name' => $actor->name,
            'changed_by_email' => $actor->email,
            'action' => $action,
            'role' => $role,
        ]);
    }
}
