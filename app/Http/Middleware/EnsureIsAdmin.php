<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    /**
     * Permite acesso à área administrativa para papéis privilegiados.
     * Compatível com o campo `role` do User (root, system, master, super-admin, admin).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = ['root', 'system', 'master', 'super-admin', 'admin'];

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Acesso restrito à área administrativa.');
        }

        return $next($request);
    }
}
