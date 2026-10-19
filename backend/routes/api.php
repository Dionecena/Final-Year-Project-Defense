<?php

use App\Http\Resources\UtilisateurResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Routes de l'API (préfixe /api). Les routes /login et /logout sont dans routes/web.php.
|
| RG01 : tout ce qui n'est pas l'accueil public, la liste des spécialités, l'inscription
| et la connexion exige une authentification (groupe auth:sanctum ci-dessous).
*/

// --- Routes publiques -------------------------------------------------------------

// --- Routes authentifiées ---------------------------------------------------------
Route::middleware(['auth:sanctum', 'compte.actif'])->group(function () {

    Route::get('/moi', fn (Request $request) => new UtilisateurResource($request->user()->load('medecin.specialite')));

});
