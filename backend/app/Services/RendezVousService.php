<?php

namespace App\Services;

use App\Enums\StatutRdv;
use App\Exceptions\CreneauDejaReserveException;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\AnnulationRendezVous;
use App\Notifications\ConfirmationRendezVous;
use App\Notifications\ModificationRendezVous;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Réservation, modification et annulation des rendez-vous (section 8.2). Les droits (RG08)
 * sont contrôlés en amont par RendezVousPolicy.
 */
class RendezVousService
{
    public function __construct(
        private readonly CreneauService $creneaux,
        private readonly OrientationService $orientation,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Réserve un créneau pour le patient ; le rendez-vous est directement confirmé (RG06).
     *
     * La spécialité suggérée n'est pas reçue du client : elle est recalculée sur la spécialité du
     * médecin. Si le patient a conservé sa spécialité malgré une suggestion, specialite_suggeree_id
     * est renseignée ; s'il a suivi la suggestion, il n'y a plus d'incompatibilité (section 8.3).
     *
     * @param  array{motif: string, symptomes: string}|null  $preconsultation  facultative (RG02)
     *
     * @throws CreneauDejaReserveException créneau pris entre-temps (409, RG07)
     */
    public function reserver(User $patient, Medecin $medecin, CarbonImmutable $dateHeure, ?array $preconsultation = null): RendezVous
    {
        $rendezVous = $this->dansUneTransaction(function () use ($patient, $medecin, $dateHeure, $preconsultation) {
            // 1. Revérification du créneau.
            $this->creneaux->verifier($medecin, $dateHeure);

            // 2. Insertion, statut « confirmé » (RG06).
            $rendezVous = RendezVous::create([
                'patient_id' => $patient->id,
                'medecin_id' => $medecin->user_id,
                'date_heure' => $dateHeure,
                'statut' => StatutRdv::Confirme->value,
            ]);

            // 3. Préconsultation éventuelle.
            if ($preconsultation !== null) {
                $rendezVous->preconsultation()->create([
                    'motif' => $preconsultation['motif'],
                    'symptomes' => $preconsultation['symptomes'],
                    'specialite_suggeree_id' => $this->orientation->specialiteSuggeree(
                        $medecin->specialite, $preconsultation['motif'], $preconsultation['symptomes'],
                    )?->id,
                    'date_saisie' => CarbonImmutable::now(),
                    // Consentement exigé par StoreRendezVousRequest avant toute préconsultation.
                    'date_consentement' => CarbonImmutable::now(),
                ]);
            }

            return $rendezVous;
        });

        // Après validation de la transaction seulement : courriel de confirmation (section 9).
        $this->charger($rendezVous);
        $this->notifications->envoyer($rendezVous, new ConfirmationRendezVous($rendezVous));

        return $rendezVous;
    }

    /**
     * Changement de créneau, avec le même médecin ou un autre médecin de la même spécialité
     * (section 8.2). Le rendez-vous repasse à « confirmé ».
     *
     * @throws CreneauDejaReserveException créneau pris entre-temps (409, RG07)
     */
    public function modifier(RendezVous $rendezVous, Medecin $medecin, CarbonImmutable $dateHeure): RendezVous
    {
        $rendezVous->loadMissing('medecin');

        if ((int) $medecin->specialite_id !== (int) $rendezVous->medecin->specialite_id) {
            throw ValidationException::withMessages(['medecin_id' => 'Le rendez-vous ne peut être déplacé que vers un médecin de la même spécialité.']);
        }

        if ((int) $medecin->user_id === (int) $rendezVous->medecin_id && $dateHeure->equalTo($rendezVous->date_heure)) {
            throw ValidationException::withMessages(['date_heure' => 'Ce rendez-vous est déjà sur ce créneau.']);
        }

        $ancienneDateHeure = CarbonImmutable::instance($rendezVous->date_heure);
        $reprogrammation = $rendezVous->statut === StatutRdv::AReprogrammer;

        $this->dansUneTransaction(function () use ($rendezVous, $medecin, $dateHeure) {
            $this->creneaux->verifier($medecin, $dateHeure);

            $rendezVous->update([
                'medecin_id' => $medecin->user_id,
                'date_heure' => $dateHeure,
                'statut' => StatutRdv::Confirme->value,
            ]);
        });

        // Après validation de la transaction seulement : courriel de modification ou de
        // reprogrammation (section 9).
        $this->charger($rendezVous);
        $this->notifications->envoyer($rendezVous, new ModificationRendezVous($rendezVous, $ancienneDateHeure, $reprogrammation));

        return $rendezVous;
    }

    /**
     * Annulation par le patient (dans le délai) ou par la secrétaire : le créneau est libéré et le
     * patient en est informé dans les deux cas (section 9).
     */
    public function annuler(RendezVous $rendezVous, User $auteur): RendezVous
    {
        $rendezVous->update(['statut' => StatutRdv::Annule->value]);

        $this->charger($rendezVous);
        $this->notifications->envoyer($rendezVous, new AnnulationRendezVous($rendezVous, parPatient: $auteur->id === (int) $rendezVous->patient_id));

        return $rendezVous;
    }

    /**
     * Exécute $operation dans une transaction ; une violation de l'index unique partiel
     * rendez_vous_creneau_actif_unique annule la transaction et devient une erreur 409 (RG07).
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function dansUneTransaction(callable $operation): mixed
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $e) {
            throw new CreneauDejaReserveException($e);
        }
    }

    private function charger(RendezVous $rendezVous): RendezVous
    {
        return $rendezVous->load(['medecin.user', 'medecin.specialite', 'patient']);
    }
}
