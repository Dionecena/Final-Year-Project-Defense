<?php

namespace Tests\Feature;

use App\Enums\StatutRdv;
use App\Models\Disponibilite;
use App\Models\Indisponibilite;
use App\Models\Preconsultation;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 4 : disponibilités (RG14) et consultation des plannings (RG12).
 */
class PlanningsTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaire;

    private User $medecin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialiteSeeder::class);
        $this->secretaire = User::factory()->secretaire()->create();
        $this->medecin = User::factory()->medecin(Specialite::where('libelle', 'Cardiologie')->first())->create();
    }

    private function plage(array $surcharge = []): array
    {
        return $surcharge + ['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 30];
    }

    private function disponibilite(array $surcharge = []): Disponibilite
    {
        return Disponibilite::create($surcharge + ['medecin_id' => $this->medecin->id] + $this->plage());
    }

    // --- RG14 / T13 -----------------------------------------------------------------

    public function test_t13_aucune_route_de_modification_ni_de_suppression_d_une_disponibilite(): void
    {
        $dispo = $this->disponibilite();
        $base = "/api/medecins/{$this->medecin->id}/disponibilites";

        foreach (['putJson', 'patchJson', 'deleteJson'] as $methode) {
            foreach (["{$base}/{$dispo->id}", $base, "/api/disponibilites/{$dispo->id}"] as $url) {
                $statut = $this->actingAs($this->secretaire)->{$methode}($url, $this->plage(['heure_fin' => '13:00']))->status();
                $this->assertContains($statut, [404, 405], "{$methode} {$url} a renvoyé {$statut}");
            }
        }

        $this->assertDatabaseCount('disponibilites', 1);
        $this->assertSame('12:00:00', $dispo->fresh()->heure_fin);
    }

    // --- Ajout d'une disponibilité ----------------------------------------------------

    public function test_la_secretaire_ajoute_une_disponibilite(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson("/api/medecins/{$this->medecin->id}/disponibilites", $this->plage(['jour_semaine' => 3]))
            ->assertCreated()
            ->assertJsonPath('data.jour_semaine', 3)
            ->assertJsonPath('data.heure_debut', '09:00')
            ->assertJsonPath('data.heure_fin', '12:00')
            ->assertJsonPath('data.duree_creneau', 30);

        $this->assertDatabaseHas('disponibilites', ['medecin_id' => $this->medecin->id, 'jour_semaine' => 3, 'duree_creneau' => 30]);
    }

    public function test_validation_des_disponibilites(): void
    {
        $url = "/api/medecins/{$this->medecin->id}/disponibilites";
        $cas = [
            [[], ['jour_semaine', 'heure_debut', 'heure_fin', 'duree_creneau']],
            [['jour_semaine' => 0], ['jour_semaine']],
            [['jour_semaine' => 8], ['jour_semaine']],
            [['heure_debut' => '9h'], ['heure_debut']],
            [['heure_debut' => '12:00', 'heure_fin' => '09:00'], ['heure_fin']],
            [['heure_debut' => '09:00', 'heure_fin' => '09:00'], ['heure_fin']],
            [['duree_creneau' => 0], ['duree_creneau']],
            [['duree_creneau' => -15], ['duree_creneau']],
            [['heure_debut' => '09:00', 'heure_fin' => '09:20', 'duree_creneau' => 30], ['duree_creneau']],
        ];

        foreach ($cas as [$donnees, $champs]) {
            $corps = $donnees === [] ? [] : $this->plage($donnees);
            $this->actingAs($this->secretaire)->postJson($url, $corps)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($champs);
        }

        $this->assertDatabaseCount('disponibilites', 0);
    }

    public function test_message_en_francais_si_l_heure_de_fin_precede_le_debut(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson("/api/medecins/{$this->medecin->id}/disponibilites", $this->plage(['heure_debut' => '14:00', 'heure_fin' => '10:00']))
            ->assertJsonValidationErrors(['heure_fin' => 'L\'heure de fin doit être postérieure à l\'heure de début.']);
    }

    public function test_une_plage_qui_chevauche_une_plage_du_meme_jour_est_refusee(): void
    {
        $this->disponibilite(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);
        $url = "/api/medecins/{$this->medecin->id}/disponibilites";

        foreach ([['09:00', '12:00'], ['11:00', '13:00'], ['08:00', '09:30'], ['10:00', '11:00'], ['08:00', '13:00']] as [$debut, $fin]) {
            $this->actingAs($this->secretaire)
                ->postJson($url, $this->plage(['heure_debut' => $debut, 'heure_fin' => $fin]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['heure_debut' => 'Cette plage chevauche une disponibilité existante du même jour (09:00–12:00).']);
        }

        // Plages contiguës, autre jour ou autre médecin : acceptées.
        $this->actingAs($this->secretaire)->postJson($url, $this->plage(['heure_debut' => '12:00', 'heure_fin' => '14:00']))->assertCreated();
        $this->actingAs($this->secretaire)->postJson($url, $this->plage(['jour_semaine' => 2]))->assertCreated();
        $autre = User::factory()->medecin()->create();
        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$autre->id}/disponibilites", $this->plage())->assertCreated();

        $this->assertDatabaseCount('disponibilites', 4);
    }

    public function test_medecin_inexistant_ou_desactive(): void
    {
        $patient = User::factory()->create();
        $this->actingAs($this->secretaire)->postJson('/api/medecins/999999/disponibilites', $this->plage())->assertNotFound();
        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$patient->id}/disponibilites", $this->plage())->assertNotFound();

        $inactif = User::factory()->medecin()->inactif()->create();
        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$inactif->id}/disponibilites", $this->plage())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('medecin_id');

        $this->assertDatabaseCount('disponibilites', 0);
    }

    public function test_patient_medecin_et_administrateur_ne_peuvent_pas_ajouter_de_disponibilite(): void
    {
        $url = "/api/medecins/{$this->medecin->id}/disponibilites";

        foreach ([User::factory()->create(), $this->medecin, User::factory()->administrateur()->create()] as $acteur) {
            $this->actingAs($acteur)->postJson($url, $this->plage())->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->postJson($url, $this->plage())->assertUnauthorized();

        $this->assertDatabaseCount('disponibilites', 0);
    }

    // --- Consultation du planning -----------------------------------------------------

    public function test_la_secretaire_consulte_le_planning_d_une_semaine(): void
    {
        $this->disponibilite(['jour_semaine' => 1]);
        $this->disponibilite(['jour_semaine' => 3, 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'duree_creneau' => 20]);
        $patient = User::factory()->create(['nom' => 'Démo', 'prenom' => 'Patient']);

        // Semaine du lundi 2 novembre 2026 au dimanche 8 novembre 2026.
        $dansLaSemaine = RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 09:30:00']);
        RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 14:00:00', 'statut' => StatutRdv::Annule->value]);
        RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-09 09:00:00']);
        RendezVous::create(['patient_id' => $patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-01 09:00:00']);
        Indisponibilite::create(['medecin_id' => $this->medecin->id, 'enregistree_par' => $this->secretaire->id, 'date_debut' => '2026-10-30 00:00', 'date_fin' => '2026-11-02 23:59', 'motif' => 'Congé']);
        Indisponibilite::create(['medecin_id' => $this->medecin->id, 'enregistree_par' => $this->secretaire->id, 'date_debut' => '2026-11-10 00:00', 'date_fin' => '2026-11-11 23:59']);

        $reponse = $this->actingAs($this->secretaire)
            ->getJson("/api/medecins/{$this->medecin->id}/planning?date=2026-11-05")
            ->assertOk()
            ->assertJsonPath('data.medecin.id', $this->medecin->id)
            ->assertJsonPath('data.medecin.specialite.libelle', 'Cardiologie')
            ->assertJsonPath('data.semaine', ['du' => '2026-11-02', 'au' => '2026-11-08'])
            ->assertJsonCount(2, 'data.disponibilites')
            ->assertJsonPath('data.disponibilites.1.heure_debut', '14:00')
            ->assertJsonCount(1, 'data.indisponibilites')
            ->assertJsonPath('data.indisponibilites.0.motif', 'Congé')
            ->assertJsonCount(2, 'data.rendez_vous')
            ->assertJsonPath('data.rendez_vous.0.id', $dansLaSemaine->id)
            ->assertJsonPath('data.rendez_vous.0.date', '2026-11-02')
            ->assertJsonPath('data.rendez_vous.0.heure', '09:30')
            ->assertJsonPath('data.rendez_vous.0.statut_libelle', 'Confirmé')
            ->assertJsonPath('data.rendez_vous.0.patient', ['nom' => 'Démo', 'prenom' => 'Patient'])
            ->assertJsonPath('data.rendez_vous.1.statut', 'annule');

        $reponse->assertJsonMissingPath('data.rendez_vous.0.patient.email');
    }

    public function test_le_planning_valide_le_parametre_de_date(): void
    {
        $this->actingAs($this->secretaire)->getJson("/api/medecins/{$this->medecin->id}/planning?date=05/11/2026")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        // Sans date : la semaine en cours.
        $this->actingAs($this->secretaire)->getJson("/api/medecins/{$this->medecin->id}/planning")
            ->assertOk()
            ->assertJsonPath('data.semaine.du', now()->startOfWeek()->toDateString());
    }

    public function test_le_medecin_consulte_son_planning_sans_les_preconsultations(): void
    {
        $this->disponibilite();
        $rdv = RendezVous::create(['patient_id' => User::factory()->create()->id, 'medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 09:00:00']);
        Preconsultation::create(['rendez_vous_id' => $rdv->id, 'motif' => 'Motif fictif', 'symptomes' => 'Symptômes fictifs', 'date_saisie' => now()]);

        $reponse = $this->actingAs($this->medecin)->getJson('/api/medecin/planning?date=2026-11-02')
            ->assertOk()
            ->assertJsonPath('data.medecin.id', $this->medecin->id)
            ->assertJsonCount(1, 'data.disponibilites')
            ->assertJsonCount(1, 'data.rendez_vous');

        $this->assertStringNotContainsString('fictif', $reponse->getContent());
    }

    public function test_un_medecin_ne_voit_que_son_planning(): void
    {
        $autre = User::factory()->medecin()->create();
        Disponibilite::create(['medecin_id' => $autre->id] + $this->plage(['jour_semaine' => 5]));
        RendezVous::create(['patient_id' => User::factory()->create()->id, 'medecin_id' => $autre->id, 'date_heure' => '2026-11-06 09:00:00']);

        // Le paramètre medecin_id éventuel est ignoré : seul le planning du médecin connecté est renvoyé.
        $this->actingAs($this->medecin)->getJson("/api/medecin/planning?date=2026-11-06&medecin_id={$autre->id}")
            ->assertOk()
            ->assertJsonPath('data.medecin.id', $this->medecin->id)
            ->assertJsonCount(0, 'data.disponibilites')
            ->assertJsonCount(0, 'data.rendez_vous');

        // Les routes du secrétariat lui sont interdites.
        $this->actingAs($this->medecin)->getJson("/api/medecins/{$autre->id}/planning")->assertForbidden();
        $this->actingAs($this->medecin)->getJson('/api/medecins')->assertForbidden();
    }

    public function test_acces_aux_plannings_selon_le_role(): void
    {
        $patient = User::factory()->create();
        $admin = User::factory()->administrateur()->create();

        foreach ([$patient, $admin] as $acteur) {
            $this->actingAs($acteur)->getJson("/api/medecins/{$this->medecin->id}/planning")->assertForbidden();
            $this->actingAs($acteur)->getJson('/api/medecin/planning')->assertForbidden();
            $this->actingAs($acteur)->getJson('/api/medecins')->assertForbidden();
        }
        $this->actingAs($this->secretaire)->getJson('/api/medecin/planning')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/medecins/{$this->medecin->id}/planning")->assertUnauthorized();
        $this->getJson('/api/medecin/planning')->assertUnauthorized();
    }

    public function test_la_secretaire_liste_les_medecins(): void
    {
        User::factory()->medecin()->inactif()->create(['nom' => 'Zulu']);

        $this->actingAs($this->secretaire)->getJson('/api/medecins')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->medecin->id)
            ->assertJsonPath('data.0.actif', true)
            ->assertJsonPath('data.0.specialite.libelle', 'Cardiologie')
            ->assertJsonPath('data.1.actif', false)
            ->assertJsonMissingPath('data.0.email');
    }
}
