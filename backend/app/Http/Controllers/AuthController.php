<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Http\Requests\Auth\InscriptionRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use App\Services\ComptesParAppareil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Authentification de la SPA par session (Sanctum en mode SPA).
 */
class AuthController extends Controller
{
    /** POST /api/inscription : seul le patient crée lui-même son compte. */
    public function inscription(InscriptionRequest $request, ComptesParAppareil $appareil): JsonResponse
    {
        // Le nouveau compte est connecté aussitôt : il compte parmi les comptes de l'appareil.
        abort_unless($appareil->autorise($request->ip()), 429, ComptesParAppareil::MESSAGE);

        $user = User::create($request->safe()->only(['nom', 'prenom', 'email', 'telephone', 'password']) + [
            'role' => Role::Patient->value,
            'actif' => true,
            'date_consentement' => now(),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $appareil->enregistrer($request->ip(), $user->id);

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

    /** GET /demo/secretariat : 204 si la connexion de démonstration est ouverte, 404 sinon. */
    public function demoSecretariatDisponible(): Response
    {
        $this->compteDemoSecretariat();

        return response()->noContent();
    }

    /** POST /demo/secretariat : connexion d'un clic au compte secrétaire de démonstration. */
    public function demoSecretariat(Request $request): UtilisateurResource
    {
        $user = $this->compteDemoSecretariat();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UtilisateurResource($user);
    }

    /** Compte désigné par DEMO_SECRETARIAT : une secrétaire active, sinon 404. */
    private function compteDemoSecretariat(): User
    {
        $email = config('clinique.demo_secretariat');
        $user = $email ? User::where('email', $email)->where('role', Role::Secretaire->value)->where('actif', true)->first() : null;
        abort_if($user === null, 404);

        return $user;
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
