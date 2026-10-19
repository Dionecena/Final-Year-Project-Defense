<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Gestion des comptes : réservée à l'administrateur.
 */
class UtilisateurPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }

    public function view(User $acteur, User $cible): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }

    public function create(User $acteur): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }

    public function update(User $acteur, User $cible): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }

    public function desactiver(User $acteur, User $cible): Response
    {
        if (! $acteur->aLeRole(Role::Administrateur)) {
            return Response::deny('Accès refusé.');
        }

        if ($acteur->is($cible)) {
            return Response::deny('Vous ne pouvez pas désactiver votre propre compte.');
        }

        return Response::allow();
    }
}
