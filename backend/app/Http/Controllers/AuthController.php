<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Http\Requests\Auth\InscriptionRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Authentification de la SPA par session (Sanctum en mode SPA).
 */
class AuthController extends Controller
{
    /** POST /api/inscription : seul le patient crée lui-même son compte. */
    public function inscription(InscriptionRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['nom', 'prenom', 'email', 'telephone', 'password']) + [
            'role' => Role::Patient->value,
            'actif' => true,
            'date_consentement' => now(),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UtilisateurResource($user))->response()->setStatusCode(201);
    }

    /** POST /login */
    public function connexion(ConnexionRequest $request): UtilisateurResource
    {
        $user = $request->utilisateurAuthentifie();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UtilisateurResource($user->load('medecin.specialite'));
    }

    /** POST /logout */
    public function deconnexion(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Vous êtes déconnecté.']);
    }

    /** GET /api/moi */
    public function moi(Request $request): UtilisateurResource
    {
        return new UtilisateurResource($request->user()->load('medecin.specialite'));
    }
}
