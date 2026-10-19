<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Règles communes aux formulaires de compte (inscription, création et modification par
 * l'administrateur).
 */
trait ReglesUtilisateur
{
    protected function regleNom(bool $obligatoire = true): array
    {
        return [$obligatoire ? 'required' : 'sometimes', 'string', 'max:100'];
    }

    protected function regleEmail(?int $ignorerId = null, bool $obligatoire = true): array
    {
        return [
            $obligatoire ? 'required' : 'sometimes',
            'string',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($ignorerId),
        ];
    }

    protected function regleTelephone(bool $obligatoire = true): array
    {
        return [$obligatoire ? 'required' : 'sometimes', 'string', 'max:30', 'regex:/^\+?[0-9 .\-]{6,30}$/'];
    }

    protected function reglePassword(bool $obligatoire = true): array
    {
        return [$obligatoire ? 'required' : 'sometimes', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()];
    }

    /** Adresse en minuscules et espaces superflus retirés avant validation. */
    protected function normaliserChamps(): void
    {
        $donnees = [];
        foreach (['nom', 'prenom', 'telephone'] as $champ) {
            if ($this->has($champ) && is_string($this->input($champ))) {
                $donnees[$champ] = trim($this->input($champ));
            }
        }
        if ($this->has('email') && is_string($this->input('email'))) {
            $donnees['email'] = Str::lower(trim($this->input('email')));
        }
        $this->merge($donnees);
    }
}
