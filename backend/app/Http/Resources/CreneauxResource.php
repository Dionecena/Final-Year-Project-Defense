<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Créneaux libres d'une période, regroupés par jour (section 8.1, étape 5).
 *
 * Ressource : ['du' => CarbonImmutable, 'au' => CarbonImmutable, 'jours' => Collection<date, créneaux>].
 */
class CreneauxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'du' => $r['du']->toDateString(),
            'au' => $r['au']->toDateString(),
            'jours' => $r['jours']->map(fn (Collection $creneaux, string $date) => [
                'date' => $date,
                'creneaux' => CreneauResource::collection($creneaux->values()),
            ])->values(),
        ];
    }
}
