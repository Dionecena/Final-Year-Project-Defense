<?php

use App\Http\Middleware\EnsureCompteActif;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum en mode SPA : session + cookie + protection CSRF pour les requêtes du frontend.
        $middleware->statefulApi();

        // Derrière ngrok, l'application reçoit les requêtes via un proxy HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
            'compte.actif' => EnsureCompteActif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // L'API, /login et /logout répondent toujours en JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'login', 'logout') || $request->expectsJson()
        );
    })->create();
