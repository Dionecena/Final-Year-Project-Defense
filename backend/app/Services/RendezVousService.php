<?php

namespace App\Services;

use App\Enums\StatutRdv;
use App\Exceptions\CreneauDejaReserveException;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\User;
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
    public function __construct(private readonly CreneauService $creneaux) {}

    /**
     * Réserve un créneau pour le patient ; le rendez-vous est directement confirmé (RG06).
     *
     * @param  array{motif: string, symptomes: string, specialite_suggeree_id?: int|null}|null  $preconsultation  facultative (RG02), validée au module 6
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
                    'specialite_suggeree_id' => $preconsultation['specialite_suggeree_id'] ?? null,
                    'date_saisie' => CarbonImmutable::now(),
                ]);
            }

            return $rendezVous;
        });

        // Module 8 : après validation de la transaction seulement, placer le courriel de
        // confirmation dans la file d'attente.

        return $this->charger($rendezVous);
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

        $this->dansUneTransaction(function () use ($rendezVous, $medecin, $dateHeure) {
            $this->creneaux->verifier($medecin, $dateHeure);

            $rendezVous->update([
                'medecin_id' => $medecin->user_id,
                'date_heure' => $dateHeure,
                'statut' => StatutRdv::Confirme->value,
            ]);
        });

        // Module 8 : après validation de la transaction seulement, courriel de modification.

        return $this->charger($rendezVous);
    }

    /** Annulation par le patient (dans le délai) ou par la secrétaire : le créneau est libéré. */
    public function annuler(RendezVous $rendezVous): RendezVous
    {
        $rendezVous->update(['statut' => StatutRdv::Annule->value]);

        // Module 8 : courriel d'annulation.

        return $this->charger($rendezVous);
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
