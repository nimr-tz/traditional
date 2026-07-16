<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Super admins are implicitly allowed everywhere; every other role must
     * be named explicitly on the route, e.g. `role:reviewer,admin`.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && ($user->isSuperAdmin() || in_array($user->role, $roles, true)), 403);

        return $next($request);
    }
}
