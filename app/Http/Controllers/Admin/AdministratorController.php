<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministratorAccessChange;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdministratorController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $users = User::query()
            ->where('is_admin', false)
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
        ]);

        DB::transaction(function () use ($data, $request) {
            $target = User::query()->lockForUpdate()->findOrFail($data['user_id']);

            if ($target->is_admin) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user already has administrator access.',
                ]);
            }

            if (! $target->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'user_id' => 'The user must verify their email before becoming an administrator.',
                ]);
            }

            $target->forceFill(['is_admin' => true])->save();
            $this->recordChange($target, $request->user(), 'granted');
        });

        return back()->with('success', 'Administrator access granted.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $target->is_admin) {
                throw ValidationException::withMessages([
                    'administrator' => 'This user does not have administrator access.',
                ]);
            }

            if ($target->is($request->user())) {
                throw ValidationException::withMessages([
                    'administrator' => 'You cannot remove your own administrator access. Ask another administrator to remove it.',
                ]);
            }

            $administrators = User::query()
                ->where('is_admin', true)
                ->lockForUpdate()
                ->get(['id']);

            if ($administrators->count() <= 1) {
                throw ValidationException::withMessages([
                    'administrator' => 'The final administrator cannot be removed.',
                ]);
            }

            $target->forceFill(['is_admin' => false])->save();
            $this->recordChange($target, $request->user(), 'revoked');
        });

        return back()->with('success', 'Administrator access removed.');
    }

    private function recordChange(User $target, User $actor, string $action): void
    {
        AdministratorAccessChange::create([
            'target_user_id' => $target->id,
            'target_name' => $target->name,
            'target_email' => $target->email,
            'changed_by' => $actor->id,
            'changed_by_name' => $actor->name,
            'changed_by_email' => $actor->email,
            'action' => $action,
        ]);
    }
}
