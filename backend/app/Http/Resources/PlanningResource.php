<?php

namespace App\Http\Resources;

use App\Models\Indisponibilite;
use App\Models\RendezVous;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Planning hebdomadaire d'un médecin : disponibilités, indisponibilités et rendez-vous de la
 * semaine. Aucune donnée de préconsultation (RG10, module 6). Dates et heures exprimées dans le
 * fuseau de l'application (Africa/Dakar).
 *
 * Ressource : tableau construit par PlanningService::semaine().
 */
class PlanningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'medecin' => new MedecinResource($p['medecin']),
            'semaine' => [
                'du' => $p['du']->toDateString(),
                'au' => $p['au']->toDateString(),
            ],
            'disponibilites' => DisponibiliteResource::collection($p['disponibilites']),
            'indisponibilites' => $p['indisponibilites']->map(fn (Indisponibilite $i) => [
                'id' => $i->id,
                'date_debut' => $i->date_debut->format('Y-m-d H:i'),
                'date_fin' => $i->date_fin->format('Y-m-d H:i'),
                'motif' => $i->motif,
            ])->values(),
            'rendez_vous' => $p['rendez_vous']->map(fn (RendezVous $r) => [
                'id' => $r->id,
                'date' => $r->date_heure->toDateString(),
                'heure' => $r->date_heure->format('H:i'),
                'statut' => $r->statut->value,
                'statut_libelle' => $r->statut->libelle(),
                'patient' => [
                    'nom' => $r->patient->nom,
                    'prenom' => $r->patient->prenom,
                ],
            ])->values(),
        ];
    }
}
