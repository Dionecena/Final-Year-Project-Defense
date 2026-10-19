<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
| Connexion et déconnexion de la SPA (Sanctum : session, cookie et jeton CSRF).
*/
Route::post('/login', [AuthController::class, 'connexion']);
Route::post('/logout', [AuthController::class, 'deconnexion'])->middleware('auth:web');

/*
| Démonstration : la SPA React compilée (npm run build) est servie par Laravel depuis
| public/spa. Toute URL qui n'est ni l'API ni Sanctum renvoie index.html, et React
| Router prend le relais.
*/
Route::get('/{chemin?}', function () {
    $index = public_path('spa/index.html');

    abort_unless(is_file($index), 404, 'Frontend non compilé : lancez « npm run build » dans frontend/.');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('chemin', '^(?!api/|api$|sanctum/|up$).*$');
