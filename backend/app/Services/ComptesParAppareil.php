<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Limite le nombre de comptes patients différents qui se connectent (ou sont créés) depuis une même
 * adresse IP : au plus MAXIMUM comptes par heure (demande de l'auteur, contre les scripts qui
 * enchaînent les comptes). Le personnel n'est pas concerné, pour que la clinique et la
 * démonstration puissent utiliser les quatre rôles depuis un même poste.
 */
class ComptesParAppareil
{
    public const MAXIMUM = 3;

    public const MESSAGE = 'Trop de comptes différents ont été utilisés depuis cet appareil. Réessayez dans une heure.';

    /** Le compte (déjà vu, ou nouveau s'il reste de la place) peut-il se connecter depuis cette adresse ? */
    public function autorise(string $ip, ?int $compte = null): bool
    {
        $comptes = Cache::get($this->cle($ip), []);

        return in_array($compte, $comptes, true) || count($comptes) < self::MAXIMUM;
    }

    // shortcut: lecture puis écriture sans verrou, deux connexions simultanées peuvent dépasser
    // le maximum d'une unité ; passer à Cache::lock() si la limite doit être stricte.
    public function enregistrer(string $ip, int $compte): void
    {
        $comptes = Cache::get($this->cle($ip), []);

        if (! in_array($compte, $comptes, true)) {
            // La liste est oubliée une heure après le dernier nouveau compte.
            Cache::put($this->cle($ip), [...$comptes, $compte], now()->addHour());
        }
    }

    private function cle(string $ip): string
    {
        return 'comptes-patients|'.$ip;
    }
}
