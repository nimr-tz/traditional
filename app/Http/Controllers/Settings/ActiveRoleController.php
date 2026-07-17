<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActiveRoleController extends Controller
{
    /**
     * Switch which of the user's assigned roles they're currently viewing —
     * a cosmetic preference only (nav + default landing page). It never
     * changes what the account is authorized to do: every route allowed by
     * the user's full set of roles stays reachable regardless of which one
     * is active.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        if (! $user->hasRole($data['role'])) {
            throw ValidationException::withMessages([
                'role' => 'You do not hold that role.',
            ]);
        }

        $user->forceFill(['active_role' => $data['role']])->save();

        return redirect()->to(User::homeRouteForRole($data['role']));
    }
}
