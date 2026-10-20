<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Indisponibilité d'un médecin. Dates dans le fuseau Africa/Dakar.
 *
 * @mixin \App\Models\Indisponibilite
 */
class IndisponibiliteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medecin_id' => $this->medecin_id,
            'date_debut' => $this->date_debut->format('Y-m-d H:i'),
            'date_fin' => $this->date_fin->format('Y-m-d H:i'),
            'motif' => $this->motif,
        ];
    }
}
