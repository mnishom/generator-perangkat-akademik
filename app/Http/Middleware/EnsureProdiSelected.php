<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProdiSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->prodi_id && ! $user->isAdmin()) {
            return redirect()->route('prodi.select');
        }

        return $next($request);
    }
}
