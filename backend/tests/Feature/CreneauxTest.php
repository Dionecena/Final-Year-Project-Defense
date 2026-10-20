<?php

namespace Tests\Feature;

use App\Enums\StatutRdv;
use App\Models\Disponibilite;
use App\Models\Indisponibilite;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use App\Services\CreneauService;
use Carbon\CarbonImmutable;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 5 : calcul des créneaux (section 8.1, RG05). Date de référence fixe :
 * lundi 2 novembre 2026, fuseau Africa/Dakar.
 */
class CreneauxTest extends TestCase
{
    use RefreshDatabase;

    private Specialite $cardiologie;

    private User $secretaire;

    private User $medecin;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Dakar'));

        $this->seed(SpecialiteSeeder::class);
        $this->cardiologie = Specialite::where('libelle', 'Cardiologie')->firstOrFail();
        $this->secretaire = User::factory()->secretaire()->create();
        $this->medecin = $this->medecinAvecPlage(['nom' => 'Alpha']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** Médecin de cardiologie disponible le lundi de 09:00 à 12:00, créneaux de 30 minutes. */
    private function medecinAvecPlage(array $attributs = [], int $jour = 1, string $debut = '09:00', string $fin = '12:00', int $duree = 30): User
    {
        $medecin = User::factory()->medecin($this->cardiologie)->create($attributs);
        Disponibilite::create(['medecin_id' => $medecin->id, 'jour_semaine' => $jour, 'heure_debut' => $debut, 'heure_fin' => $fin, 'duree_creneau' => $duree]);

        return $medecin;
    }

    private function heures(string $url, string $date): array
    {
        $jours = collect($this->getJson($url)->assertOk()->json('data.jours'));

        return collect($jours->firstWhere('date', $date)['creneaux'] ?? [])->pluck('heure')->all();
    }

    public function test_t03_creneaux_indisponibles_et_reserves_absents(): void
    {
        $patient = User::factory()->create();
        Indisponibilite::create([
            'medecin_id' => $this->medecin->id, 'enregistree_par' => $this->secretaire->id,
            'date_debut' => '2026-11-02 10:00', 'date_fin' => '2026-11-02 10:59', 'motif' => 'Réunion',
        ]);
        RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 09:30']);
        RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 11:00', 'statut' => StatutRdv::AReprogrammer->value]);
        // Un rendez-vous annulé libère son créneau.
        RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 11:30', 'statut' => StatutRdv::Annule->value]);

        $this->assertSame(
            ['09:00', '11:30'],
            $this->heures("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-02&au=2026-11-02", '2026-11-02'),
        );

        // La semaine suivante, sans indisponibilité ni rendez-vous : la plage complète.
        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->heures("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-09&au=2026-11-09", '2026-11-09'),
        );
    }

    public function test_une_indisponibilite_de_plusieurs_jours_retire_tous_les_creneaux_couverts(): void
    {
        Indisponibilite::create([
            'medecin_id' => $this->medecin->id, 'enregistree_par' => $this->secretaire->id,
            'date_debut' => '2026-11-01 00:00', 'date_fin' => '2026-11-09 23:59',
        ]);

        $jours = $this->getJson("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-02&au=2026-11-16")->assertOk()->json('data.jours');

        $this->assertSame(['2026-11-16'], array_column($jours, 'date'));
    }

    public function test_le_decoupage_ignore_le_dernier_creneau_incomplet(): void
    {
        // Mercredi 14:00–15:40, créneaux de 30 min : 15:30–16:00 dépasserait la plage.
        Disponibilite::create(['medecin_id' => $this->medecin->id, 'jour_semaine' => 3, 'heure_debut' => '14:00', 'heure_fin' => '15:40', 'duree_creneau' => 30]);

        $reponse = $this->getJson("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-04&au=2026-11-04")->assertOk();

        $this->assertSame(['14:00', '14:30', '15:00'], array_column($reponse->json('data.jours.0.creneaux'), 'heure'));
        $reponse->assertJsonPath('data.jours.0.creneaux.2.fin', '15:30')
            ->assertJsonPath('data.jours.0.creneaux.0.date_heure', '2026-11-04 14:00');
    }

    public function test_les_creneaux_passes_sont_retires(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:10', 'Africa/Dakar'));

        $this->assertSame(
            ['10:30', '11:00', '11:30'],
            $this->heures("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-02&au=2026-11-02", '2026-11-02'),
        );
    }

    public function test_un_medecin_desactive_ne_propose_aucun_creneau(): void
    {
        $this->medecin->update(['actif' => false]);
        $autre = $this->medecinAvecPlage(['nom' => 'Beta'], 1, '14:00', '15:00');

        $this->getJson("/api/medecins/{$this->medecin->id}/creneaux")->assertOk()->assertJsonPath('data.jours', []);

        $creneaux = collect($this->getJson("/api/specialites/{$this->cardiologie->id}/creneaux?du=2026-11-02&au=2026-11-02")->json('data.jours.0.creneaux'));
        $this->assertSame([$autre->id], $creneaux->pluck('medecin.id')->unique()->values()->all());
    }

    public function test_creneaux_d_une_specialite_tries_et_regroupes_par_jour(): void
    {
        $beta = $this->medecinAvecPlage(['nom' => 'Beta', 'prenom' => 'Démo'], 1, '09:00', '10:00', 60);
        Disponibilite::create(['medecin_id' => $beta->id, 'jour_semaine' => 3, 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'duree_creneau' => 20]);
        // Un médecin d'une autre spécialité n'apparaît pas.
        $orl = User::factory()->medecin(Specialite::where('libelle', 'ORL')->first())->create();
        Disponibilite::create(['medecin_id' => $orl->id, 'jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'duree_creneau' => 30]);

        $reponse = $this->getJson("/api/specialites/{$this->cardiologie->id}/creneaux?du=2026-11-02&au=2026-11-04")
            ->assertOk()
            ->assertJsonPath('data.du', '2026-11-02')
            ->assertJsonPath('data.au', '2026-11-04')
            ->assertJsonCount(2, 'data.jours')
            ->assertJsonPath('data.jours.0.date', '2026-11-02')
            ->assertJsonPath('data.jours.1.date', '2026-11-04')
            ->assertJsonPath('data.jours.0.creneaux.1.medecin', ['id' => $beta->id, 'nom' => 'Beta', 'prenom' => 'Démo']);

        $lundi = collect($reponse->json('data.jours.0.creneaux'))->map(fn ($c) => $c['heure'].' '.$c['medecin']['nom'])->all();
        $this->assertSame(['09:00 Alpha', '09:00 Beta', '09:30 Alpha', '10:00 Alpha', '10:30 Alpha', '11:00 Alpha', '11:30 Alpha'], $lundi);
        $this->assertSame(['08:00', '08:20', '08:40'], array_column($reponse->json('data.jours.1.creneaux'), 'heure'));
    }

    public function test_prochains_creneaux_de_la_specialite(): void
    {
        $beta = $this->medecinAvecPlage(['nom' => 'Beta'], 1, '08:30', '09:30', 60);
        RendezVous::create(['patient_id' => User::factory()->create()->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 09:00']);

        $creneaux = app(CreneauService::class)->prochainsCreneaux($this->cardiologie);

        $this->assertSame(
            ['2026-11-02 08:30 '.$beta->id, '2026-11-02 09:30 '.$this->medecin->id, '2026-11-02 10:00 '.$this->medecin->id],
            $creneaux->map(fn ($c) => $c['debut']->format('Y-m-d H:i').' '.$c['medecin']->user_id)->all(),
        );
        $this->assertCount(1, app(CreneauService::class)->prochainsCreneaux($this->cardiologie, 1));
    }

    public function test_periode_par_defaut_et_bornes(): void
    {
        $url = "/api/medecins/{$this->medecin->id}/creneaux";

        // Par défaut : d'aujourd'hui à la fin de l'horizon (30 jours, aujourd'hui compris).
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.du', '2026-11-02')
            ->assertJsonPath('data.au', '2026-12-01')
            ->assertJsonCount(5, 'data.jours');

        // Un début passé est ramené à aujourd'hui.
        $this->getJson("{$url}?du=2026-10-01&au=2026-11-02")->assertOk()->assertJsonPath('data.du', '2026-11-02');

        $this->getJson("{$url}?du=02/11/2026")->assertUnprocessable()->assertJsonValidationErrors('du');
        $this->getJson("{$url}?du=2026-11-10&au=2026-11-09")->assertUnprocessable()->assertJsonValidationErrors('au');
        $this->getJson("{$url}?au=2026-12-02")->assertUnprocessable()
            ->assertJsonValidationErrors(['au' => 'Les créneaux sont proposés au plus 30 jours à l\'avance (jusqu\'au 01/12/2026).']);

        $this->getJson('/api/medecins/999999/creneaux')->assertNotFound();
        $this->getJson('/api/specialites/999999/creneaux')->assertNotFound();
    }

    public function test_les_creneaux_sont_calcules_dans_le_fuseau_de_dakar(): void
    {
        $this->assertSame('Africa/Dakar', config('app.timezone'));
        $this->assertSame('Africa/Dakar', CarbonImmutable::now()->tzName);

        // 09:15 à Dakar exprimé dans un autre fuseau : le créneau de 09:00 est passé, pas celui de 09:30.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:15', 'Europe/Paris'));

        $this->assertSame(
            ['09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->heures("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-02&au=2026-11-02", '2026-11-02'),
        );
    }
}
