<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\RendezVous;
use App\Models\User;

/**
 * RG10 : le contenu d'une préconsultation n'est lisible que par le médecin du rendez-vous et par
 * le patient qui l'a saisie. La secrétaire voit seulement si elle existe ; l'administrateur n'y a
 * pas accès. Contrôle fait sur le rendez-vous, pour ne rien révéler aux autres utilisateurs.
 */
class PreconsultationPolicy
{
    public function view(User $acteur, RendezVous $rendezVous): bool
    {
        return match ($acteur->role) {
            Role::Patient => (int) $rendezVous->patient_id === $acteur->id,
            Role::Medecin => (int) $rendezVous->medecin_id === $acteur->id,
            default => false,
        };
    }
}
