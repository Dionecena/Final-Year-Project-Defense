<?php

namespace App\Http\Requests\Concerns;

use App\Models\Specialite;

/**
 * Règles de la préconsultation (RG02, RG11, section 10 : collecte minimale, motif et
 * symptômes seulement).
 */
trait ReglesPreconsultation
{
    /**
     * @param  string  $prefixe  'preconsultation.' pour un objet imbriqué, '' pour des champs à la racine
     * @param  bool  $obligatoire  false : champs exigés seulement si la préconsultation est fournie
     */
    protected function reglesPreconsultation(?Specialite $specialite, string $prefixe = '', bool $obligatoire = true): array
    {
        $exigence = $obligatoire ? 'required' : 'required_with:'.rtrim($prefixe, '.');
        // Sans spécialité valide (erreur signalée sur son propre champ), la limite générale s'applique.
        $max = $specialite?->maxSymptomes() ?? (int) config('preconsultation.max_symptomes');

        return [
            $prefixe.'motif' => [$exigence, 'string', 'max:255'],
            $prefixe.'symptomes' => [$exigence, 'string', 'max:'.$max],
        ];
    }

    protected function messagesPreconsultation(string $prefixe = ''): array
    {
        return [
            $prefixe.'motif.required' => 'Le motif est obligatoire pour la préconsultation.',
            $prefixe.'motif.required_with' => 'Le motif est obligatoire pour la préconsultation.',
            $prefixe.'symptomes.required' => 'Les symptômes sont obligatoires pour la préconsultation.',
            $prefixe.'symptomes.required_with' => 'Les symptômes sont obligatoires pour la préconsultation.',
            $prefixe.'symptomes.max' => 'Les symptômes ne doivent pas dépasser :max caractères.',
        ];
    }
}
