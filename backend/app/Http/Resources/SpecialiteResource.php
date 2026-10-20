<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Specialite
 */
class SpecialiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            // Jours de présence communiqués par la clinique.
            'description' => $this->description,
            // Uniquement dans la liste de l'administrateur.
            'nombre_medecins' => $this->whenCounted('medecins'),
        ];
    }
}
