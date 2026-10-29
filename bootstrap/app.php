<?php

use App\Http\Middleware\EnsureAccountCanLogin;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureProdiSelected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [EnsureAccountCanLogin::class]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'prodi' => EnsureProdiSelected::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
