<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Statistiques de l'administrateur : des comptes seulement, sans donnée nominative ni contenu
 * de préconsultation (RG10).
 *
 * Ressource : tableau construit par StatistiqueService::calculer().
 */
class StatistiquesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
