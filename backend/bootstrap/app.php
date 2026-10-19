<?php

use App\Http\Middleware\EnsureCompteActif;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Messages d'erreur en français ; un message explicite (abort(403, '…')) est conservé.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*', 'login', 'logout') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Les informations saisies sont invalides.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*', 'login', 'logout') || $request->expectsJson()) {
                return response()->json(['message' => 'Vous devez être connecté.'], 401);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! ($request->is('api/*', 'login', 'logout') || $request->expectsJson())) {
                return null;
            }

            $parDefaut = [
                403 => 'Accès refusé.',
                404 => 'Ressource introuvable.',
                405 => 'Méthode non autorisée.',
                419 => 'Votre session a expiré. Rechargez la page.',
                429 => 'Trop de requêtes. Veuillez patienter.',
            ];
            $message = $e->getMessage();
            $anglais = $message === '' || str_starts_with($message, 'This action is unauthorized')
                || str_starts_with($message, 'No query results') || str_starts_with($message, 'The route')
                || str_contains($message, 'method is not supported') || $message === 'CSRF token mismatch.'
                || $message === 'Too Many Attempts.';

            if ($anglais && isset($parDefaut[$e->getStatusCode()])) {
                return response()->json(['message' => $parDefaut[$e->getStatusCode()]], $e->getStatusCode(), $e->getHeaders());
            }

            return null;
        });
    })->create();
