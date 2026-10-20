<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Disponibilite
 */
class DisponibiliteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medecin_id' => $this->medecin_id,
            'jour_semaine' => $this->jour_semaine,
            'heure_debut' => substr($this->heure_debut, 0, 5),
            'heure_fin' => substr($this->heure_fin, 0, 5),
            'duree_creneau' => $this->duree_creneau,
        ];
    }
}
