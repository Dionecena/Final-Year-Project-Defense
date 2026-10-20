<?php

namespace App\Services;

use App\Models\Notification as Journal;
use App\Models\RendezVous;
use App\Notifications\NotificationRendezVous;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Envoi des courriels au patient et journal des envois (section 9). Chaque envoi crée une ligne
 * dans la table notifications (objet, contenu) ; date_envoi n'est renseignée qu'une fois l'envoi
 * réussi, par marquerEnvoyee() à la réception de NotificationSent. Le canal « database » de
 * Laravel n'est pas utilisé.
 */
class NotificationService
{
    /** Journalise puis place le courriel dans la file d'attente (envoi après validation de la transaction). */
    public function envoyer(RendezVous $rendezVous, NotificationRendezVous $notification): void
    {
        $journal = Journal::create([
            'rendez_vous_id' => $rendezVous->id,
            'objet' => $notification->objet,
            'contenu' => $notification->contenu(),
        ]);

        $notification->journalId = $journal->id;

        $rendezVous->loadMissing('patient')->patient->notify($notification);
    }

    /** Écouteur de NotificationSent (enregistré dans AppServiceProvider). */
    public static function marquerEnvoyee(NotificationSent $evenement): void
    {
        if ($evenement->channel !== 'mail' || ! $evenement->notification instanceof NotificationRendezVous || $evenement->notification->journalId === null) {
            return;
        }

        Journal::whereKey($evenement->notification->journalId)->update(['date_envoi' => now()]);
    }
}
