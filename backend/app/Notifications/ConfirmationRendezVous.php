<?php

namespace App\Notifications;

use App\Models\RendezVous;

/** Section 9, événement 1 : confirmation d'un rendez-vous (RG06). */
class ConfirmationRendezVous extends NotificationRendezVous
{
    protected function construireObjet(): string
    {
        return 'Confirmation de votre rendez-vous';
    }

    protected function introduction(RendezVous $rendezVous): array
    {
        return ['Votre rendez-vous est confirmé :'];
    }

    protected function conclusion(): array
    {
        return [
            sprintf(
                'Vous pouvez le modifier ou l\'annuler depuis votre espace jusqu\'à %d heures avant son heure ; passé ce délai, veuillez contacter la clinique.',
                (int) config('rendezvous.delai_modification_heures'),
            ),
            ...parent::conclusion(),
        ];
    }
}
