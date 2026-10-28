<?php

namespace Database\Seeders;

use App\Models\Specialite;
use Illuminate\Database\Seeder;

/**
 * Données réelles communiquées par la secrétaire de la clinique : spécialités et jours de présence.
 */
class SpecialiteSeeder extends Seeder
{
    /** @var array<string, string> libellé => jours de présence */
    public const SPECIALITES = [
        'Médecine générale' => 'Tous les jours',
        'Pédiatrie' => 'Tous les jours',
        'ORL' => 'Lundi, mercredi, vendredi',
        'Cardiologie' => 'Lundi, mercredi',
        'Ophtalmologie' => 'Mardi',
        'Psychiatrie' => 'Lundi, vendredi, samedi',
        'Dermatologie' => 'Mercredi, samedi',
        'Neurologie' => 'Mercredi',
        'Neurochirurgie' => 'Jeudi',
        'Chirurgie générale' => 'Jeudi',
        'Gynécologie' => 'Vendredi',
    ];

    public function run(): void
    {
        foreach (self::SPECIALITES as $libelle => $jours) {
            Specialite::updateOrCreate(
                ['libelle' => $libelle],
                ['description' => 'Jours de présence : '.$jours],
            );
        }
    }
}
