<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Créneau libre calculé par CreneauService (tableau medecin, debut, fin), dans le fuseau
 * Africa/Dakar. date_heure est la valeur à renvoyer pour réserver.
 */
class CreneauResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'date_heure' => $c['debut']->format('Y-m-d H:i'),
            'date' => $c['debut']->toDateString(),
            'heure' => $c['debut']->format('H:i'),
            'fin' => $c['fin']->format('H:i'),
            'medecin' => [
                'id' => $c['medecin']->user_id,
                'nom' => $c['medecin']->user->nom,
                'prenom' => $c['medecin']->user->prenom,
            ],
        ];
    }
}
