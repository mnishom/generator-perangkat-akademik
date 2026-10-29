<?php

namespace App\Http\Middleware;

use App\Core\Modules\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route dengan parameter {module} hanya untuk modul terdaftar yang boleh diakses pengguna. */
class EnsureModuleAccess
{
    public function __construct(private readonly ModuleAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->route('module');
        abort_unless($request->user() && $this->access->canAccess($request->user(), $key), 404);

        return $next($request);
    }
}
