<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint un groupe de routes à un ou plusieurs rôles : ->middleware('role:secretaire')
 * ou ->middleware('role:patient,secretaire').
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role?->value, $roles, true)) {
            abort(403, 'Accès refusé.');
        }

        return $next($request);
    }
}
