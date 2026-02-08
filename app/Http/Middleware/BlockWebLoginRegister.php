<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockWebLoginRegister
{
    /**
     * Block web access to /login and /register routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');

        if ($path === 'login' || $path === 'register') {
            return redirect('/');
        }

        return $next($request);
    }
}
