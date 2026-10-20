<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contenu d'une préconsultation (données de santé), réservé au médecin du rendez-vous et au
 * patient (RG10). Date de saisie dans le fuseau Africa/Dakar.
 *
 * @mixin \App\Models\Preconsultation
 */
class PreconsultationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'motif' => $this->motif,
            'symptomes' => $this->symptomes,
            'date_saisie' => $this->date_saisie?->format('Y-m-d H:i'),
            // Suggestion d'orientation émise que le patient n'a pas suivie (section 8.3).
            'specialite_suggeree' => $this->specialiteSuggeree ? [
                'id' => $this->specialiteSuggeree->id,
                'libelle' => $this->specialiteSuggeree->libelle,
            ] : null,
        ];
    }
}
