<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Médecin vu par le secrétariat (identifiant = user_id).
 *
 * @mixin \App\Models\Medecin
 */
class MedecinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'nom' => $this->user->nom,
            'prenom' => $this->user->prenom,
            'actif' => $this->user->actif,
            'specialite' => [
                'id' => $this->specialite->id,
                'libelle' => $this->specialite->libelle,
            ],
        ];
    }
}
