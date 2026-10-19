<?php

namespace App\Enums;

enum StatutRdv: string
{
    case Confirme = 'confirme';
    case Annule = 'annule';
    case AReprogrammer = 'a_reprogrammer';
    case Passe = 'passe';

    public function libelle(): string
    {
        return match ($this) {
            self::Confirme => 'Confirmé',
            self::Annule => 'Annulé',
            self::AReprogrammer => 'À reprogrammer',
            self::Passe => 'Passé',
        };
    }

    /**
     * Statuts qui occupent un créneau (index unique partiel rendez_vous_creneau_actif_unique).
     *
     * @return list<string>
     */
    public static function actifs(): array
    {
        return [self::Confirme->value, self::AReprogrammer->value];
    }
}
