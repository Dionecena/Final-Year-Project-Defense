<?php

use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SpecialiteController;
use Illuminate\Support\Facades\Route;

/*
| Routes de l'API (préfixe /api). Les routes /login et /logout sont dans routes/web.php.
|
| RG01 : tout ce qui n'est pas l'accueil public, la liste des spécialités, l'inscription
| et la connexion exige une authentification (groupe auth:sanctum ci-dessous).
*/

// --- Routes publiques -------------------------------------------------------------
Route::post('/inscription', [AuthController::class, 'inscription']);
Route::get('/specialites', [SpecialiteController::class, 'index']);

// --- Routes authentifiées ---------------------------------------------------------
Route::middleware(['auth:sanctum', 'compte.actif'])->group(function () {

    Route::get('/moi', [AuthController::class, 'moi']);

    // Administrateur
    Route::middleware('role:administrateur')->prefix('admin')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
        Route::get('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'show']);
        Route::patch('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update']);
        Route::post('/utilisateurs/{utilisateur}/desactivation', [UtilisateurController::class, 'desactiver']);
    });

});
