<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StatutRdv;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;

/**
 * Accès aux rendez-vous. RG08 : le patient ne modifie ou n'annule que ses propres rendez-vous,
 * et seulement jusqu'à delai_modification_heures avant l'heure ; la secrétaire, sans délai.
 * RG10 (côté rendez-vous) : un patient n'accède pas au rendez-vous d'un autre.
 */
class RendezVousPolicy
{
    public function view(User $acteur, RendezVous $rendezVous): bool
    {
        return match ($acteur->role) {
            Role::Patient => (int) $rendezVous->patient_id === $acteur->id,
            Role::Medecin => (int) $rendezVous->medecin_id === $acteur->id,
            Role::Secretaire => true,
            default => false,
        };
    }

    public function modifier(User $acteur, RendezVous $rendezVous): Response
    {
        return $this->gerer($acteur, $rendezVous, 'modifié');
    }

    public function annuler(User $acteur, RendezVous $rendezVous): Response
    {
        return $this->gerer($acteur, $rendezVous, 'annulé');
    }

    /** Heure limite de modification ou d'annulation en ligne par le patient (RG08). */
    public static function limitePatient(RendezVous $rendezVous): CarbonImmutable
    {
        return CarbonImmutable::instance($rendezVous->date_heure)->subHours((int) config('rendezvous.delai_modification_heures'));
    }

    private function gerer(User $acteur, RendezVous $rendezVous, string $action): Response
    {
        $estPatient = $acteur->aLeRole(Role::Patient);

        if (! $estPatient && ! $acteur->aLeRole(Role::Secretaire)) {
            return Response::deny('Accès refusé.');
        }

        if ($estPatient && (int) $rendezVous->patient_id !== $acteur->id) {
            return Response::deny('Ce rendez-vous ne vous appartient pas.');
        }

        // Un rendez-vous annulé ou passé ne se modifie plus.
        if (! in_array($rendezVous->statut->value, StatutRdv::actifs(), true)) {
            return Response::deny("Ce rendez-vous est « {$rendezVous->statut->libelle()} » : il ne peut plus être {$action}.");
        }

        $maintenant = CarbonImmutable::now();

        // Heure dépassée mais statut pas encore passé à « passé » par la tâche planifiée.
        if ($rendezVous->date_heure->lessThanOrEqualTo($maintenant)) {
            return Response::deny("L'heure de ce rendez-vous est dépassée : il ne peut plus être {$action}.");
        }

        if ($estPatient && $maintenant->greaterThanOrEqualTo(self::limitePatient($rendezVous))) {
            return Response::deny(sprintf(
                'Un rendez-vous ne peut plus être %s en ligne moins de %d heures avant son heure. Veuillez contacter la clinique.',
                $action,
                (int) config('rendezvous.delai_modification_heures'),
            ));
        }

        return Response::allow();
    }
}
