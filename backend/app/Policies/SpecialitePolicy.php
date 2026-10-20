<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Specialite;
use App\Models\User;

/**
 * Gestion des spécialités : réservée à l'administrateur. La liste publique
 * (GET /api/specialites) ne passe pas par cette politique (RG01). Aucune suppression.
 */
class SpecialitePolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }

    public function create(User $acteur): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }

    public function update(User $acteur, Specialite $specialite): bool
    {
        return $acteur->aLeRole(Role::Administrateur);
    }
}
