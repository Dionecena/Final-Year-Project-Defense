<?php

namespace App\Services;

use App\Models\Medecin;
use Carbon\CarbonImmutable;

/**
 * Consultation du planning d'un médecin, en lecture seule (RG12 pour le médecin, RG14 pour
 * les disponibilités).
 */
class PlanningService
{
    /**
     * Semaine (lundi à dimanche) qui contient $date.
     *
     * @return array<string, mixed>
     */
    public function semaine(Medecin $medecin, CarbonImmutable $date): array
    {
        $du = $date->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $finExclue = $du->addWeek();

        return [
            'medecin' => $medecin->loadMissing('user', 'specialite'),
            'du' => $du,
            'au' => $finExclue->subDay(),
            'disponibilites' => $medecin->disponibilites()->orderBy('jour_semaine')->orderBy('heure_debut')->get(),
            'indisponibilites' => $medecin->indisponibilites()
                ->where('date_debut', '<', $finExclue)
                ->where('date_fin', '>=', $du)
                ->orderBy('date_debut')
                ->get(),
            // Tous les statuts, y compris « annulé » (décision de l'auteur), affichés avec leur libellé.
            'rendez_vous' => $medecin->rendezVous()
                ->with('patient:id,nom,prenom')
                ->where('date_heure', '>=', $du)
                ->where('date_heure', '<', $finExclue)
                ->orderBy('date_heure')
                ->orderBy('id')
                ->get(),
        ];
    }
}
