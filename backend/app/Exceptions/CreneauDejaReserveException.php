<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * RG07 : le créneau a été pris entre-temps (revérification ou violation de l'index unique
 * rendez_vous_creneau_actif_unique). Renvoyée en 409 avec un message clair.
 */
class CreneauDejaReserveException extends ConflictHttpException
{
    public function __construct(?\Throwable $precedente = null)
    {
        parent::__construct(
            'Ce créneau vient d\'être réservé par quelqu\'un d\'autre. Veuillez choisir un autre créneau.',
            $precedente,
        );
    }
}
