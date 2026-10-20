<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Policies\RendezVousPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rendez-vous vu par le patient ou la secrétaire. Dates dans le fuseau Africa/Dakar.
 * Aucune donnée de préconsultation (RG10) : seulement, si la requête l'a chargé avec
 * withExists('preconsultation'), le fait qu'elle existe (liste de la secrétaire).
 *
 * @mixin \App\Models\RendezVous
 */
class RendezVousResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $acteur = $request->user();

        return [
            'id' => $this->id,
            'date_heure' => $this->date_heure->format('Y-m-d H:i'),
            'date' => $this->date_heure->toDateString(),
            'heure' => $this->date_heure->format('H:i'),
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'medecin' => [
                'id' => $this->medecin->user_id,
                'nom' => $this->medecin->user->nom,
                'prenom' => $this->medecin->user->prenom,
            ],
            'specialite' => [
                'id' => $this->medecin->specialite->id,
                'libelle' => $this->medecin->specialite->libelle,
            ],
            // Le patient connaît ses propres coordonnées ; la secrétaire en a besoin pour le joindre.
            'patient' => $this->when(! $acteur?->aLeRole(Role::Patient), fn () => [
                'id' => $this->patient->id,
                'nom' => $this->patient->nom,
                'prenom' => $this->patient->prenom,
                'telephone' => $this->patient->telephone,
            ]),
            'a_preconsultation' => $this->when(isset($this->preconsultation_exists), fn () => (bool) $this->preconsultation_exists),
            // Droits de l'utilisateur connecté (RG08), pour l'affichage des boutons.
            'modifiable' => (bool) $acteur?->can('modifier', $this->resource),
            'annulable' => (bool) $acteur?->can('annuler', $this->resource),
            'limite_modification' => RendezVousPolicy::limitePatient($this->resource)->format('Y-m-d H:i'),
        ];
    }
}
