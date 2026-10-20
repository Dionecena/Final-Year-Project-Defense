<?php

namespace App\Services;

use App\Models\Specialite;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Suggestion d'orientation indicative (section 8.3, RG03, RG04) à partir des règles de
 * config/orientation.php. Ce n'est pas un diagnostic.
 */
class OrientationService
{
    public function __construct(private readonly CreneauService $creneaux) {}

    /**
     * Vérifie la compatibilité du motif et des symptômes avec la spécialité choisie.
     *
     * @return array{specialite: Specialite, creneaux: Collection<int, array>}|null null : aucune suggestion
     */
    public function verifier(Specialite $choisie, string $motif, string $symptomes): ?array
    {
        $suggeree = $this->specialiteSuggeree($choisie, $motif, $symptomes);

        return $suggeree === null ? null : [
            'specialite' => $suggeree,
            'creneaux' => $this->creneaux->prochainsCreneaux($suggeree, 3),
        ];
    }

    /**
     * Aucune suggestion si aucune règle ne s'applique ou si la spécialité choisie fait partie des
     * correspondances ; sinon la spécialité qui a le plus de termes trouvés. En cas d'égalité, la
     * première dans l'ordre de config/orientation.php l'emporte (décision de l'auteur).
     */
    public function specialiteSuggeree(Specialite $choisie, string $motif, string $symptomes): ?Specialite
    {
        $correspondances = $this->correspondances($motif.' '.$symptomes);

        if ($correspondances === [] || array_key_exists($choisie->libelle, $correspondances)) {
            return null;
        }

        // Spécialités existantes (un libellé de la configuration peut avoir été renommé).
        $specialites = Specialite::whereIn('libelle', array_keys($correspondances))->get()->keyBy('libelle');

        $meilleure = null;
        $maximum = 0;
        foreach ($correspondances as $libelle => $nombre) {
            if ($specialites->has($libelle) && $nombre > $maximum) {
                $meilleure = $specialites[$libelle];
                $maximum = $nombre;
            }
        }

        return $meilleure;
    }

    /**
     * Nombre de termes distincts trouvés par spécialité, dans l'ordre de la configuration.
     * Un terme est trouvé s'il apparaît au début d'un mot du texte normalisé (« oreille » trouve
     * « oreilles », mais « vue » ne trouve pas « prevue »).
     *
     * @return array<string, int>
     */
    public function correspondances(string $texte): array
    {
        $texte = ' '.self::normaliser($texte);
        $resultat = [];

        foreach (config('orientation.regles', []) as $libelle => $termes) {
            $nombre = collect($termes)
                ->map(fn (string $terme) => self::normaliser($terme))
                ->filter()
                ->unique()
                ->filter(fn (string $terme) => str_contains($texte, ' '.$terme))
                ->count();

            if ($nombre > 0) {
                $resultat[$libelle] = $nombre;
            }
        }

        return $resultat;
    }

    /** Minuscules, accents et ponctuation retirés, espaces réduits : « Mal à l'Œil ! » → « mal a l oeil ». */
    public static function normaliser(string $texte): string
    {
        $texte = Str::ascii(mb_strtolower($texte, 'UTF-8'));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texte));
    }
}
