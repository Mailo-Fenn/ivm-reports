<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// пускает на портал только после ввода общего логина и пароля (см. config/portal.php)
class PortalAuth
{
    public const SESSION_KEY = 'portal_auth';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(self::SESSION_KEY) === true) {
            return $next($request);
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            abort(401);
        }

        return redirect()->guest(route('login'));
    }
}
