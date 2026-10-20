<?php

namespace App\Notifications;

use App\Models\RendezVous;

/** Section 9, événement 3 : annulation d'un rendez-vous, par le patient ou par la secrétaire. */
class AnnulationRendezVous extends NotificationRendezVous
{
    /** @param  bool  $parPatient  faux : annulé par la secrétaire */
    public function __construct(RendezVous $rendezVous, public bool $parPatient)
    {
        parent::__construct($rendezVous);
    }

    protected function construireObjet(): string
    {
        return 'Annulation de votre rendez-vous';
    }

    protected function introduction(RendezVous $rendezVous): array
    {
        return [$this->parPatient
            ? 'Nous vous confirmons l\'annulation de votre rendez-vous :'
            : 'La clinique a annulé votre rendez-vous :'];
    }

    protected function conclusion(): array
    {
        return [
            'Vous pouvez prendre un nouveau rendez-vous en ligne à tout moment.',
            ...parent::conclusion(),
        ];
    }
}
