<?php

namespace App\Services;

use App\Enums\StatutRdv;
use App\Models\Indisponibilite;
use App\Models\Preconsultation;
use App\Models\RendezVous;
use App\Models\Specialite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Statistiques du tableau de bord de l'administrateur (section 13). Uniquement des comptes
 * obtenus par requêtes agrégées : aucune donnée nominative, aucun contenu de préconsultation.
 *
 * Période : [du 00:00, au + 1 jour 00:00[, heure de Dakar. Les rendez-vous et les
 * préconsultations sont rattachés à la date du rendez-vous ; une indisponibilité est comptée
 * si elle touche la période.
 */
class StatistiqueService
{
    /**
     * @return array{
     *     periode: array{du: string, au: string, regroupement: string},
     *     rendez_vous: array{total: int, par_statut: list<array>, par_specialite: list<array>, par_mois: list<array>},
     *     preconsultations: array{total: int, suggestions: int},
     *     indisponibilites: array{total: int},
     * }
     */
    public function calculer(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $debut = $du->startOfDay();
        $fin = $au->startOfDay()->addDay();

        $parStatut = $this->rendezVous($debut, $fin)
            ->selectRaw('statut, count(*) as nombre')
            ->groupBy('statut')
            ->pluck('nombre', 'statut');

        $parSpecialite = $this->rendezVous($debut, $fin)
            ->join('medecins', 'medecins.user_id', '=', 'rendez_vous.medecin_id')
            ->selectRaw('medecins.specialite_id, count(*) as nombre')
            ->groupBy('medecins.specialite_id')
            ->pluck('nombre', 'medecins.specialite_id');

        $parMois = $this->rendezVous($debut, $fin)
            ->selectRaw("to_char(date_heure, 'YYYY-MM') as mois, count(*) as nombre")
            ->groupByRaw("to_char(date_heure, 'YYYY-MM')")
            ->pluck('nombre', 'mois');

        $preconsultations = Preconsultation::query()
            ->join('rendez_vous', 'rendez_vous.id', '=', 'preconsultations.rendez_vous_id')
            ->where('rendez_vous.date_heure', '>=', $debut)
            ->where('rendez_vous.date_heure', '<', $fin)
            ->selectRaw('count(*) as total, count(preconsultations.specialite_suggeree_id) as suggestions')
            ->toBase()
            ->first();

        $indisponibilites = Indisponibilite::query()
            ->where('date_debut', '<', $fin)
            ->where('date_fin', '>=', $debut)
            ->count();

        return [
            'periode' => [
                'du' => $du->toDateString(),
                'au' => $au->toDateString(),
                'regroupement' => 'mois',
            ],
            'rendez_vous' => [
                'total' => (int) $parStatut->sum(),
                // Les quatre statuts, toujours dans le même ordre, même à zéro.
                'par_statut' => array_map(fn (StatutRdv $statut) => [
                    'statut' => $statut->value,
                    'libelle' => $statut->libelle(),
                    'nombre' => (int) ($parStatut[$statut->value] ?? 0),
                ], StatutRdv::cases()),
                // Toutes les spécialités (une spécialité sans rendez-vous apparaît à zéro).
                'par_specialite' => Specialite::query()
                    ->orderBy('libelle')
                    ->get(['id', 'libelle'])
                    ->map(fn (Specialite $s) => [
                        'specialite_id' => $s->id,
                        'libelle' => $s->libelle,
                        'nombre' => (int) ($parSpecialite[$s->id] ?? 0),
                    ])
                    ->sortByDesc('nombre')
                    ->values()
                    ->all(),
                'par_mois' => $this->moisDeLaPeriode($du, $au)
                    ->map(fn (string $mois) => ['mois' => $mois, 'nombre' => (int) ($parMois[$mois] ?? 0)])
                    ->all(),
            ],
            'preconsultations' => [
                'total' => (int) $preconsultations->total,
                // Suggestions enregistrées : seulement celles que le patient n'a pas suivies (module 6).
                'suggestions' => (int) $preconsultations->suggestions,
            ],
            'indisponibilites' => [
                'total' => $indisponibilites,
            ],
        ];
    }

    /** Rendez-vous dont la date tombe dans [$debut, $fin[, tous statuts confondus. */
    private function rendezVous(CarbonImmutable $debut, CarbonImmutable $fin): Builder
    {
        return RendezVous::query()
            ->where('rendez_vous.date_heure', '>=', $debut)
            ->where('rendez_vous.date_heure', '<', $fin)
            ->toBase();
    }

    /** Mois (AAAA-MM) couverts par la période, y compris ceux sans rendez-vous. */
    private function moisDeLaPeriode(CarbonImmutable $du, CarbonImmutable $au): Collection
    {
        $mois = collect();
        for ($m = $du->startOfMonth(); $m->lessThanOrEqualTo($au); $m = $m->addMonth()) {
            $mois->push($m->format('Y-m'));
        }

        return $mois;
    }
}
