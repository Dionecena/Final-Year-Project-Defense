<?php

namespace App\Services;

use App\Enums\StatutRdv;
use App\Models\Indisponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enregistrement d'une indisponibilité (section 8.4, RG09). La secrétaire traite ensuite chaque
 * rendez-vous « à reprogrammer » par les routes existantes de modification et d'annulation.
 */
class IndisponibiliteService
{
    /**
     * Crée l'indisponibilité et passe à « à reprogrammer » les rendez-vous confirmés du médecin
     * dont l'heure tombe dans [date_debut, date_fin] (bornes comprises) et n'est pas encore passée.
     *
     * @return array{indisponibilite: Indisponibilite, rendez_vous: Collection<int, RendezVous>}
     */
    public function enregistrer(User $secretaire, Medecin $medecin, CarbonImmutable $debut, CarbonImmutable $fin, ?string $motif = null): array
    {
        // 1. date_fin >= date_debut (aussi garanti par la contrainte CHECK de la table).
        if ($fin->lessThan($debut)) {
            throw ValidationException::withMessages(['date_fin' => 'La date de fin doit être postérieure ou égale à la date de début.']);
        }

        [$indisponibilite, $ids] = DB::transaction(function () use ($secretaire, $medecin, $debut, $fin, $motif) {
            // 2. Création, enregistree_par = secrétaire connectée.
            $indisponibilite = Indisponibilite::create([
                'medecin_id' => $medecin->user_id,
                'enregistree_par' => $secretaire->id,
                'date_debut' => $debut,
                'date_fin' => $fin,
                'motif' => $motif,
            ]);

            // 3. Rendez-vous confirmés de la période → à reprogrammer. Verrouillés pour ne pas
            // manquer une modification concurrente.
            $ids = RendezVous::query()
                ->where('medecin_id', $medecin->user_id)
                ->where('statut', StatutRdv::Confirme->value)
                ->where('date_heure', '>=', $debut)
                ->where('date_heure', '<=', $fin)
                ->where('date_heure', '>', CarbonImmutable::now())
                ->lockForUpdate()
                ->pluck('id');

            RendezVous::query()->whereIn('id', $ids)->update(['statut' => StatutRdv::AReprogrammer->value]);

            return [$indisponibilite, $ids];
        });

        $rendezVous = RendezVous::query()
            ->whereIn('id', $ids)
            ->with(['medecin.user', 'medecin.specialite', 'patient'])
            ->withExists('preconsultation')
            ->orderBy('date_heure')
            ->get();

        // Module 8 : après validation de la transaction, notifier chaque patient concerné
        // (aucun courriel si la liste est vide).

        return ['indisponibilite' => $indisponibilite, 'rendez_vous' => $rendezVous];
    }
}
