<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UtilisateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role?->value,
            'actif' => $this->actif,
            'specialite' => $this->whenLoaded('medecin', fn () => $this->medecin?->specialite ? [
                'id' => $this->medecin->specialite->id,
                'libelle' => $this->medecin->specialite->libelle,
            ] : null),
        ];
    }
}
