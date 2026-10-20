<?php

namespace App\Notifications;

use App\Models\RendezVous;
use Carbon\CarbonInterface;

/**
 * Section 9, événement 2 : modification d'un rendez-vous (par le patient ou la secrétaire) ou
 * reprogrammation d'un rendez-vous « à reprogrammer » par la secrétaire (section 8.4).
 */
class ModificationRendezVous extends NotificationRendezVous
{
    /**
     * @param  CarbonInterface  $ancienneDateHeure  créneau remplacé
     * @param  bool  $reprogrammation  le rendez-vous était « à reprogrammer »
     */
    public function __construct(RendezVous $rendezVous, private CarbonInterface $ancienneDateHeure, public bool $reprogrammation = false)
    {
        parent::__construct($rendezVous);
    }

    protected function construireObjet(): string
    {
        return $this->reprogrammation ? 'Reprogrammation de votre rendez-vous' : 'Modification de votre rendez-vous';
    }

    protected function introduction(RendezVous $rendezVous): array
    {
        return [
            sprintf(
                'Votre rendez-vous du %s a été %s. Nouveau rendez-vous, confirmé :',
                self::dateHeure($this->ancienneDateHeure),
                $this->reprogrammation ? 'reprogrammé par la clinique' : 'modifié',
            ),
        ];
    }
}
