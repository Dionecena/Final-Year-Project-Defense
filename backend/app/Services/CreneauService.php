<?php

namespace App\Services;

use App\Exceptions\CreneauDejaReserveException;
use App\Models\Disponibilite;
use App\Models\Indisponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Calcul des créneaux libres (section 8.1, RG05).
 *
 * Un créneau est un tableau ['medecin' => Medecin, 'debut' => CarbonImmutable, 'fin' => CarbonImmutable],
 * dans le fuseau de l'application (Africa/Dakar). Les médecins désactivés ne proposent aucun créneau.
 * Les indisponibilités et les rendez-vous de la période sont chargés en une requête chacun.
 */
class CreneauService
{
    /**
     * Période par défaut : d'aujourd'hui au dernier jour de l'horizon (horizon_jours jours, aujourd'hui compris).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function horizon(): array
    {
        $du = CarbonImmutable::today();

        return [$du, $du->addDays(max(1, (int) config('rendezvous.horizon_jours')) - 1)];
    }

    /** @return Collection<string, list<array>> créneaux libres d'un médecin, regroupés par jour (AAAA-MM-JJ) */
    public function pourMedecin(Medecin $medecin, CarbonImmutable $du, CarbonImmutable $au): Collection
    {
        $medecin->loadMissing('user', 'disponibilites');

        return $this->regrouperParJour($this->calculer(collect([$medecin]), $du, $au));
    }

    /** @return Collection<string, list<array>> créneaux libres de tous les médecins actifs de la spécialité, par jour */
    public function pourSpecialite(Specialite $specialite, CarbonImmutable $du, CarbonImmutable $au): Collection
    {
        return $this->regrouperParJour($this->calculer($this->medecinsActifs($specialite), $du, $au));
    }

    /**
     * Les $n prochains créneaux libres de la spécialité, tous médecins confondus, sur l'horizon.
     *
     * @return Collection<int, array>
     */
    public function prochainsCreneaux(Specialite $specialite, int $n = 3): Collection
    {
        [$du, $au] = $this->horizon();

        return $this->calculer($this->medecinsActifs($specialite), $du, $au)->take($n)->values();
    }

    /**
     * Revérification d'un créneau avant réservation ou modification (RG05).
     *
     * @throws ValidationException créneau inexistant, passé, hors horizon, indisponibilité, médecin désactivé (422)
     * @throws CreneauDejaReserveException créneau déjà réservé (409, RG07)
     */
    public function verifier(Medecin $medecin, CarbonImmutable $dateHeure): void
    {
        $medecin->loadMissing('user', 'disponibilites');

        if (! $medecin->user?->actif) {
            throw ValidationException::withMessages(['medecin_id' => 'Ce médecin ne reçoit plus de rendez-vous en ligne.']);
        }

        if ($dateHeure->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages(['date_heure' => 'Ce créneau est déjà passé.']);
        }

        [, $dernierJour] = $this->horizon();
        if ($dateHeure->startOfDay()->greaterThan($dernierJour)) {
            throw ValidationException::withMessages(['date_heure' => sprintf(
                'Les rendez-vous se prennent au plus %d jours à l\'avance.',
                (int) config('rendezvous.horizon_jours'),
            )]);
        }

        $creneau = $medecin->disponibilites
            ->where('jour_semaine', $dateHeure->dayOfWeekIso)
            ->flatMap(fn (Disponibilite $d) => $this->decouper($d, $dateHeure->startOfDay()))
            ->first(fn (array $c) => $c[0]->equalTo($dateHeure));

        if ($creneau === null) {
            throw ValidationException::withMessages(['date_heure' => 'Ce créneau ne correspond à aucune disponibilité du médecin.']);
        }

        $indisponible = Indisponibilite::query()
            ->where('medecin_id', $medecin->user_id)
            ->where('date_debut', '<', $creneau[1])
            ->where('date_fin', '>=', $creneau[0])
            ->exists();

        if ($indisponible) {
            throw ValidationException::withMessages(['date_heure' => 'Le médecin est indisponible sur ce créneau.']);
        }

        $reserve = RendezVous::query()->actifs()
            ->where('medecin_id', $medecin->user_id)
            ->where('date_heure', $dateHeure)
            ->exists();

        if ($reserve) {
            throw new CreneauDejaReserveException;
        }
    }

    /**
     * Étapes 1 à 4 de la section 8.1 pour un ensemble de médecins, triées par heure puis par médecin.
     *
     * @param  Collection<int, Medecin>  $medecins  avec user et disponibilites chargés
     * @return Collection<int, array>
     */
    private function calculer(Collection $medecins, CarbonImmutable $du, CarbonImmutable $au): Collection
    {
        $medecins = $medecins->filter(fn (Medecin $m) => $m->user?->actif)->values();
        $du = $du->startOfDay();
        $au = $au->startOfDay();

        if ($medecins->isEmpty() || $au->lessThan($du)) {
            return collect();
        }

        $ids = $medecins->pluck('user_id');
        $debutPeriode = $du;
        $finPeriode = $au->addDay();

        // Indisponibilités qui touchent la période, par médecin.
        $indisponibilites = Indisponibilite::query()
            ->whereIn('medecin_id', $ids)
            ->where('date_debut', '<', $finPeriode)
            ->where('date_fin', '>=', $debutPeriode)
            ->get(['medecin_id', 'date_debut', 'date_fin'])
            ->groupBy('medecin_id');

        // Créneaux déjà occupés (confirme ou a_reprogrammer), indexés « medecin|AAAA-MM-JJ HH:MM ».
        $occupes = RendezVous::query()->actifs()
            ->whereIn('medecin_id', $ids)
            ->where('date_heure', '>=', $debutPeriode)
            ->where('date_heure', '<', $finPeriode)
            ->get(['medecin_id', 'date_heure'])
            ->mapWithKeys(fn (RendezVous $r) => [$r->medecin_id.'|'.$r->date_heure->format('Y-m-d H:i') => true]);

        $maintenant = CarbonImmutable::now();
        $creneaux = [];

        for ($jour = $du; $jour->lessThanOrEqualTo($au); $jour = $jour->addDay()) {
            foreach ($medecins as $medecin) {
                foreach ($medecin->disponibilites->where('jour_semaine', $jour->dayOfWeekIso) as $disponibilite) {
                    foreach ($this->decouper($disponibilite, $jour) as [$debut, $fin]) {
                        if ($debut->lessThanOrEqualTo($maintenant)
                            || isset($occupes[$medecin->user_id.'|'.$debut->format('Y-m-d H:i')])
                            || $this->chevaucheIndisponibilite($debut, $fin, $indisponibilites->get($medecin->user_id))) {
                            continue;
                        }

                        $creneaux[] = ['medecin' => $medecin, 'debut' => $debut, 'fin' => $fin];
                    }
                }
            }
        }

        return collect($creneaux)
            ->sortBy([
                fn (array $a, array $b) => $a['debut'] <=> $b['debut'],
                fn (array $a, array $b) => [$a['medecin']->user->nom, $a['medecin']->user_id] <=> [$b['medecin']->user->nom, $b['medecin']->user_id],
            ])
            ->values();
    }

    /**
     * Découpe la plage [heure_debut, heure_fin[ du jour donné en créneaux de duree_creneau minutes ;
     * un créneau qui dépasserait heure_fin est ignoré.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function decouper(Disponibilite $disponibilite, CarbonImmutable $jour): array
    {
        $debut = $jour->setTimeFromTimeString($disponibilite->heure_debut);
        $finPlage = $jour->setTimeFromTimeString($disponibilite->heure_fin);
        $creneaux = [];

        while (($fin = $debut->addMinutes($disponibilite->duree_creneau))->lessThanOrEqualTo($finPlage)) {
            $creneaux[] = [$debut, $fin];
            $debut = $fin;
        }

        return $creneaux;
    }

    /**
     * Une indisponibilité couvre [date_debut, date_fin], bornes comprises (section 8.4 : un rendez-vous
     * dont l'heure tombe dans la période est concerné). Le créneau couvre [debut, fin[.
     *
     * @param  Collection<int, Indisponibilite>|null  $indisponibilites
     */
    private function chevaucheIndisponibilite(CarbonImmutable $debut, CarbonImmutable $fin, ?Collection $indisponibilites): bool
    {
        return (bool) $indisponibilites?->contains(
            fn (Indisponibilite $i) => $i->date_debut->lessThan($fin) && $i->date_fin->greaterThanOrEqualTo($debut)
        );
    }

    /** @return Collection<int, Medecin> */
    private function medecinsActifs(Specialite $specialite): Collection
    {
        return Medecin::query()
            ->where('specialite_id', $specialite->id)
            ->whereHas('user', fn ($q) => $q->where('actif', true))
            ->with(['user', 'disponibilites'])
            ->get();
    }

    /**
     * @param  Collection<int, array>  $creneaux  triés
     * @return Collection<string, list<array>>
     */
    private function regrouperParJour(Collection $creneaux): Collection
    {
        return $creneaux->groupBy(fn (array $c) => $c['debut']->toDateString());
    }
}
