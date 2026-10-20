<?php

namespace Tests\Feature;

use App\Enums\StatutRdv;
use App\Models\Indisponibilite;
use App\Models\Preconsultation;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module 9 : statistiques de l'administrateur (GET /api/admin/statistiques).
 * Date de référence fixe : mardi 20 octobre 2026 à 10:00, fuseau Africa/Dakar.
 */
class StatistiquesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    private User $cardiologue;

    private User $pediatre;

    private User $patient;

    private Specialite $cardiologie;

    private Specialite $pediatrie;

    private Specialite $dermatologie;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 10:00', 'Africa/Dakar'));

        $this->seed(SpecialiteSeeder::class);
        $this->cardiologie = Specialite::where('libelle', 'Cardiologie')->firstOrFail();
        $this->pediatrie = Specialite::where('libelle', 'Pédiatrie')->firstOrFail();
        $this->dermatologie = Specialite::where('libelle', 'Dermatologie')->firstOrFail();

        $this->admin = User::factory()->administrateur()->create();
        $this->secretaire = User::factory()->secretaire()->create();
        $this->cardiologue = User::factory()->medecin($this->cardiologie)->create();
        $this->pediatre = User::factory()->medecin($this->pediatrie)->create();
        $this->patient = User::factory()->create(['nom' => 'Fictif', 'prenom' => 'Patientnominatif']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function rdv(User $medecin, string $dateHeure, StatutRdv $statut = StatutRdv::Confirme): RendezVous
    {
        return RendezVous::create([
            'patient_id' => $this->patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure,
            'statut' => $statut->value,
        ]);
    }

    private function preconsultation(RendezVous $rdv, ?Specialite $suggeree = null): void
    {
        Preconsultation::create([
            'rendez_vous_id' => $rdv->id,
            'motif' => 'Motifsecret fictif',
            'symptomes' => 'Symptomessecrets fictifs',
            'specialite_suggeree_id' => $suggeree?->id,
        ]);
    }

    private function indisponibilite(User $medecin, string $debut, string $fin): void
    {
        Indisponibilite::create([
            'medecin_id' => $medecin->id,
            'enregistree_par' => $this->secretaire->id,
            'date_debut' => $debut,
            'date_fin' => $fin,
        ]);
    }

    /** Jeu de données fictif : 6 rendez-vous en septembre et octobre 2026, 1 en dehors. */
    private function jeuDeDonnees(): void
    {
        $a = $this->rdv($this->cardiologue, '2026-09-07 09:00', StatutRdv::Passe);
        $b = $this->rdv($this->cardiologue, '2026-09-14 09:00', StatutRdv::Annule);
        $this->rdv($this->cardiologue, '2026-10-26 09:00');
        $c = $this->rdv($this->cardiologue, '2026-10-28 09:00', StatutRdv::AReprogrammer);
        $this->rdv($this->pediatre, '2026-10-21 10:00');
        $d = $this->rdv($this->pediatre, '2026-09-30 23:30', StatutRdv::Passe);
        // Hors période (mars 2026).
        $e = $this->rdv($this->pediatre, '2026-03-02 09:00', StatutRdv::Passe);

        $this->preconsultation($a);
        $this->preconsultation($b, $this->dermatologie);
        $this->preconsultation($c);
        $this->preconsultation($d, $this->cardiologie);
        $this->preconsultation($e, $this->cardiologie);

        // Dans la période, à cheval sur son début, puis hors période.
        $this->indisponibilite($this->cardiologue, '2026-10-28 08:00', '2026-10-28 12:00');
        $this->indisponibilite($this->pediatre, '2026-08-31 08:00', '2026-09-01 12:00');
        $this->indisponibilite($this->pediatre, '2026-03-01 08:00', '2026-03-02 12:00');
    }

    private function statistiques(string $parametres = '?du=2026-09-01&au=2026-10-31', ?User $acteur = null)
    {
        return $this->actingAs($acteur ?? $this->admin)->getJson('/api/admin/statistiques'.$parametres);
    }

    /** @return array<string, int> */
    private static function parCle(array $lignes, string $cle): array
    {
        return collect($lignes)->pluck('nombre', $cle)->all();
    }

    public function test_acces_refuse_aux_autres_roles_et_aux_visiteurs(): void
    {
        $this->getJson('/api/admin/statistiques')->assertUnauthorized();

        foreach ([$this->patient, $this->secretaire, $this->cardiologue] as $acteur) {
            $this->statistiques('', $acteur)->assertForbidden();
        }

        $this->statistiques('')->assertOk();
    }

    public function test_comptes_exacts_sur_un_jeu_de_donnees_fictif(): void
    {
        $this->jeuDeDonnees();

        $data = $this->statistiques()->assertOk()->json('data');

        $this->assertSame(['du' => '2026-09-01', 'au' => '2026-10-31', 'regroupement' => 'mois'], $data['periode']);
        $this->assertSame(6, $data['rendez_vous']['total']);

        $this->assertSame(
            ['confirme' => 2, 'annule' => 1, 'a_reprogrammer' => 1, 'passe' => 2],
            self::parCle($data['rendez_vous']['par_statut'], 'statut'),
        );
        $this->assertSame('À reprogrammer', collect($data['rendez_vous']['par_statut'])->firstWhere('statut', 'a_reprogrammer')['libelle']);

        $parSpecialite = self::parCle($data['rendez_vous']['par_specialite'], 'libelle');
        $this->assertCount(10, $parSpecialite);
        $this->assertSame(4, $parSpecialite['Cardiologie']);
        $this->assertSame(2, $parSpecialite['Pédiatrie']);
        $this->assertSame(0, $parSpecialite['Dermatologie']);
        $this->assertSame('Cardiologie', $data['rendez_vous']['par_specialite'][0]['libelle']);

        // Le rendez-vous du 30 septembre à 23:30 reste en septembre (heure de Dakar).
        $this->assertSame(['2026-09' => 3, '2026-10' => 3], self::parCle($data['rendez_vous']['par_mois'], 'mois'));

        $this->assertSame(['total' => 4, 'suggestions' => 2], $data['preconsultations']);
        $this->assertSame(['total' => 2], $data['indisponibilites']);
    }

    public function test_aucune_donnee_nominative_ni_contenu_de_preconsultation(): void
    {
        $this->jeuDeDonnees();

        $contenu = $this->statistiques()->assertOk()->getContent();

        foreach (['Fictif', 'Patientnominatif', $this->patient->email, 'Motifsecret', 'Symptomessecrets', 'patient_id', 'email', 'telephone'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $contenu);
        }
    }

    public function test_filtrage_par_periode(): void
    {
        $this->jeuDeDonnees();

        // Octobre seulement.
        $data = $this->statistiques('?du=2026-10-01&au=2026-10-31')->assertOk()->json('data');
        $this->assertSame(3, $data['rendez_vous']['total']);
        $this->assertSame(['confirme' => 2, 'annule' => 0, 'a_reprogrammer' => 1, 'passe' => 0], self::parCle($data['rendez_vous']['par_statut'], 'statut'));
        $this->assertSame(['2026-10' => 3], self::parCle($data['rendez_vous']['par_mois'], 'mois'));
        $this->assertSame(['total' => 1, 'suggestions' => 0], $data['preconsultations']);
        $this->assertSame(['total' => 1], $data['indisponibilites']);

        // Bornes comprises : le dernier jour inclut toute la journée.
        $data = $this->statistiques('?du=2026-09-30&au=2026-09-30')->assertOk()->json('data');
        $this->assertSame(1, $data['rendez_vous']['total']);
        $this->assertSame(['total' => 1, 'suggestions' => 1], $data['preconsultations']);
        $this->assertSame(0, $data['indisponibilites']['total']);

        // Mois sans rendez-vous présents à zéro.
        $data = $this->statistiques('?du=2026-03-01&au=2026-05-31')->assertOk()->json('data');
        $this->assertSame(['2026-03' => 1, '2026-04' => 0, '2026-05' => 0], self::parCle($data['rendez_vous']['par_mois'], 'mois'));
        $this->assertSame(['total' => 1], $data['indisponibilites']);
    }

    public function test_periode_par_defaut(): void
    {
        $this->jeuDeDonnees();

        $data = $this->statistiques('')->assertOk()->json('data');

        // Du 1er mai au 30 novembre 2026 (cinq mois en arrière, mois suivant compris).
        $this->assertSame('2026-05-01', $data['periode']['du']);
        $this->assertSame('2026-11-30', $data['periode']['au']);
        $this->assertCount(7, $data['rendez_vous']['par_mois']);
        $this->assertSame(6, $data['rendez_vous']['total']);
    }

    public function test_periode_invalide_refusee(): void
    {
        $this->statistiques('?du=2026-10-31&au=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->statistiques('?du=2024-01-01&au=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->statistiques('?du=31/10/2026')->assertUnprocessable()->assertJsonValidationErrors('du');
    }

    public function test_requetes_agregees_sans_n_plus_1(): void
    {
        $this->jeuDeDonnees();
        for ($i = 0; $i < 20; $i++) {
            $this->rdv($this->pediatre, CarbonImmutable::parse('2026-10-01 09:00')->addDays($i)->format('Y-m-d H:i'), StatutRdv::Annule);
        }

        DB::enableQueryLog();
        $this->statistiques()->assertOk();
        $requetes = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $q) => ! str_contains($q, 'personal_access_tokens') && ! str_contains($q, '"users"'));

        // Statuts, spécialités (comptes + liste), mois, préconsultations, indisponibilités.
        $this->assertLessThanOrEqual(6, $requetes->count(), $requetes->implode("\n"));
    }
}
