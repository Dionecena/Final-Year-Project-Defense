<?php

namespace App\Enums;

enum Role: string
{
    case Patient = 'patient';
    case Secretaire = 'secretaire';
    case Medecin = 'medecin';
    case Administrateur = 'administrateur';

    public function libelle(): string
    {
        return match ($this) {
            self::Patient => 'Patient',
            self::Secretaire => 'Secrétaire',
            self::Medecin => 'Médecin',
            self::Administrateur => 'Administrateur',
        };
    }

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
