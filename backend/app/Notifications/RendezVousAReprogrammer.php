<?php

namespace App\Notifications;

use App\Models\RendezVous;

/** Section 9, événement 4 : passage à « à reprogrammer » à la suite d'une indisponibilité (RG09). */
class RendezVousAReprogrammer extends NotificationRendezVous
{
    protected function construireObjet(): string
    {
        return 'Votre rendez-vous doit être reprogrammé';
    }

    protected function introduction(RendezVous $rendezVous): array
    {
        return ['Le médecin sera indisponible lors de votre rendez-vous :'];
    }

    protected function conclusion(): array
    {
        return [
            'La clinique va le reprogrammer sur un autre créneau ou vous contacter. Vous recevrez un courriel dès que le nouveau rendez-vous sera fixé.',
            ...parent::conclusion(),
        ];
    }
}
