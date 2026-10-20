<?php

namespace Tests\Feature;

use App\Enums\StatutRdv;
use App\Models\Disponibilite;
use App\Models\Indisponibilite;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use App\Notifications\RendezVousAReprogrammer;
use Carbon\CarbonImmutable;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Module 7 : enregistrement d'une indisponibilité (RG09, section 8.4), reprogrammation et
 * annulation des rendez-vous « à reprogrammer » par la secrétaire.
 * Date de référence fixe : lundi 2 novembre 2026 à 08:00, fuseau Africa/Dakar.
 */
class IndisponibilitesTest extends TestCase
{
    use RefreshDatabase;

    private Specialite $cardiologie;

    private User $secretaire;

    private User $medecin;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Dakar'));

        $this->seed(SpecialiteSeeder::class);
        $this->cardiologie = Specialite::where('libelle', 'Cardiologie')->firstOrFail();
        $this->secretaire = User::factory()->secretaire()->create();
        $this->medecin = $this->medecinDisponible();
        $this->patient = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** Médecin disponible le lundi et le mercredi de 09:00 à 12:00 (créneaux de 30 min). */
    private function medecinDisponible(): User
    {
        $medecin = User::factory()->medecin($this->cardiologie)->create();
        foreach ([1, 3] as $jour) {
            Disponibilite::create(['medecin_id' => $medecin->id, 'jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 30]);
        }

        return $medecin;
    }

    private function rdv(string $dateHeure, StatutRdv $statut = StatutRdv::Confirme, ?User $patient = null): RendezVous
    {
        return RendezVous::create([
            'patient_id' => ($patient ?? $this->patient)->id,
            'medecin_id' => $this->medecin->id,
            'date_heure' => $dateHeure,
            'statut' => $statut->value,
        ]);
    }

    private function indisponibilite(string $debut, string $fin, ?User $acteur = null, ?User $medecin = null)
    {
        return $this->actingAs($acteur ?? $this->secretaire)->postJson('/api/medecins/'.($medecin ?? $this->medecin)->id.'/indisponibilites', [
            'date_debut' => $debut,
            'date_fin' => $fin,
            'motif' => 'Congrès',
        ]);
    }

    public function test_t11_indisponibilite_couvrant_des_rendez_vous_puis_sans_rendez_vous(): void
    {
        Notification::fake();
        $autrePatient = User::factory()->create();
        $dedans1 = $this->rdv('2026-11-02 09:00');
        $dedans2 = $this->rdv('2026-11-02 10:30', patient: $autrePatient);
        $dehors = $this->rdv('2026-11-04 09:00');
        $annule = $this->rdv('2026-11-02 11:00', StatutRdv::Annule);

        $reponse = $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00')
            ->assertCreated()
            ->assertJsonPath('data.indisponibilite.date_debut', '2026-11-02 09:00')
            ->assertJsonCount(2, 'data.rendez_vous')
            ->assertJsonPath('data.rendez_vous.0.id', $dedans1->id)
            ->assertJsonPath('data.rendez_vous.1.id', $dedans2->id)
            ->assertJsonPath('data.rendez_vous.0.statut', 'a_reprogrammer');

        // La secrétaire voit le téléphone du patient pour le joindre.
        $reponse->assertJsonPath('data.rendez_vous.1.patient.telephone', $autrePatient->telephone);

        $this->assertSame(StatutRdv::AReprogrammer, $dedans1->fresh()->statut);
        $this->assertSame(StatutRdv::AReprogrammer, $dedans2->fresh()->statut);
        $this->assertSame(StatutRdv::Confirme, $dehors->fresh()->statut);
        $this->assertSame(StatutRdv::Annule, $annule->fresh()->statut);

        $this->assertDatabaseHas('indisponibilites', [
            'medecin_id' => $this->medecin->id,
            'enregistree_par' => $this->secretaire->id,
            'motif' => 'Congrès',
        ]);

        // Un courriel à chaque patient concerné, et à eux seuls.
        Notification::assertSentTo($this->patient, RendezVousAReprogrammer::class, fn (RendezVousAReprogrammer $n) => $n->rendezVousId === $dedans1->id);
        Notification::assertSentTo($autrePatient, RendezVousAReprogrammer::class, fn (RendezVousAReprogrammer $n) => $n->rendezVousId === $dedans2->id);
        Notification::assertCount(2);
        $this->assertDatabaseCount('notifications', 2);

        // Puis une indisponibilité sans rendez-vous : liste vide et aucun courriel.
        $this->indisponibilite('2026-11-09 09:00', '2026-11-09 12:00')
            ->assertCreated()
            ->assertJsonCount(0, 'data.rendez_vous');

        Notification::assertCount(2);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_t11_les_courriels_sont_places_dans_la_file_d_attente(): void
    {
        Queue::fake();
        $this->rdv('2026-11-02 09:00');
        $this->rdv('2026-11-02 10:30', patient: User::factory()->create());

        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00')->assertJsonCount(2, 'data.rendez_vous');
        Queue::assertPushed(SendQueuedNotifications::class, 2);

        $this->indisponibilite('2026-11-09 09:00', '2026-11-09 12:00')->assertJsonCount(0, 'data.rendez_vous');
        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_les_bornes_de_la_periode_sont_comprises(): void
    {
        $debut = $this->rdv('2026-11-02 09:00');
        $fin = $this->rdv('2026-11-02 10:00');
        $apres = $this->rdv('2026-11-02 10:30');

        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 10:00')->assertJsonCount(2, 'data.rendez_vous');

        $this->assertSame(StatutRdv::AReprogrammer, $debut->fresh()->statut);
        $this->assertSame(StatutRdv::AReprogrammer, $fin->fresh()->statut);
        $this->assertSame(StatutRdv::Confirme, $apres->fresh()->statut);
    }

    public function test_seuls_les_rendez_vous_du_medecin_concerne_changent(): void
    {
        $autreMedecin = User::factory()->medecin($this->cardiologie)->create();
        $autre = RendezVous::create(['patient_id' => $this->patient->id, 'medecin_id' => $autreMedecin->id, 'date_heure' => '2026-11-02 09:00', 'statut' => 'confirme']);

        $this->indisponibilite('2026-11-02 00:00', '2026-11-02 23:59')->assertJsonCount(0, 'data.rendez_vous');
        $this->assertSame(StatutRdv::Confirme, $autre->fresh()->statut);
    }

    public function test_indisponibilite_refusee_si_la_fin_precede_le_debut(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');

        $this->indisponibilite('2026-11-02 12:00', '2026-11-02 09:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_fin' => 'postérieure ou égale']);

        $this->assertDatabaseCount('indisponibilites', 0);
        $this->assertSame(StatutRdv::Confirme, $rdv->fresh()->statut);
    }

    public function test_une_indisponibilite_ponctuelle_est_acceptee(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');

        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 09:00')->assertCreated()->assertJsonCount(1, 'data.rendez_vous');
        $this->assertSame(StatutRdv::AReprogrammer, $rdv->fresh()->statut);
    }

    public function test_validation_des_champs(): void
    {
        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$this->medecin->id}/indisponibilites", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut', 'date_fin']);

        $this->indisponibilite('02/11/2026 09:00', '2026-11-02 12:00')
            ->assertJsonValidationErrors(['date_debut' => 'AAAA-MM-JJ HH:MM']);

        // Période entièrement passée.
        $this->indisponibilite('2026-11-01 09:00', '2026-11-01 12:00')
            ->assertJsonValidationErrors(['date_fin' => 'déjà passée']);

        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$this->medecin->id}/indisponibilites", [
            'date_debut' => '2026-11-02 09:00', 'date_fin' => '2026-11-02 12:00', 'motif' => str_repeat('a', 256),
        ])->assertJsonValidationErrors(['motif']);

        $this->assertDatabaseCount('indisponibilites', 0);
    }

    public function test_un_rendez_vous_deja_commence_n_est_pas_concerne(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 09:15', 'Africa/Dakar'));
        $commence = $this->rdv('2026-11-02 09:00');
        $avenir = $this->rdv('2026-11-02 09:30');

        $this->indisponibilite('2026-11-02 08:00', '2026-11-02 12:00')->assertJsonCount(1, 'data.rendez_vous');
        $this->assertSame(StatutRdv::Confirme, $commence->fresh()->statut);
        $this->assertSame(StatutRdv::AReprogrammer, $avenir->fresh()->statut);
    }

    public function test_acces_reserve_a_la_secretaire(): void
    {
        $this->postJson("/api/medecins/{$this->medecin->id}/indisponibilites", [])->assertUnauthorized();
        foreach ([$this->patient, $this->medecin, User::factory()->administrateur()->create()] as $acteur) {
            $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00', $acteur)->assertForbidden();
        }

        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00', medecin: $this->patient)->assertNotFound();

        $this->assertDatabaseCount('indisponibilites', 0);
    }

    public function test_les_creneaux_couverts_disparaissent_et_le_planning_affiche_l_indisponibilite(): void
    {
        $this->indisponibilite('2026-11-02 10:00', '2026-11-02 12:00')->assertCreated();

        $heures = collect($this->getJson("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-02&au=2026-11-02")->json('data.jours.0.creneaux'))
            ->pluck('heure')->all();
        $this->assertSame(['09:00', '09:30'], $heures);

        // Une réservation sur un créneau couvert est refusée.
        $this->actingAs($this->patient)->postJson('/api/rendez-vous', ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 10:30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_heure' => 'indisponible']);

        $this->actingAs($this->secretaire)->getJson("/api/medecins/{$this->medecin->id}/planning?date=2026-11-02")
            ->assertOk()
            ->assertJsonPath('data.indisponibilites.0.date_debut', '2026-11-02 10:00')
            ->assertJsonPath('data.indisponibilites.0.date_fin', '2026-11-02 12:00')
            ->assertJsonPath('data.indisponibilites.0.motif', 'Congrès');
    }

    public function test_la_liste_a_reprogrammer_de_la_secretaire(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');
        $this->rdv('2026-11-04 09:00');
        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00');

        $this->actingAs($this->secretaire)->getJson('/api/secretariat/rendez-vous?statut=a_reprogrammer')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rdv->id)
            ->assertJsonPath('data.0.modifiable', true)
            ->assertJsonPath('data.0.annulable', true);
    }

    public function test_la_secretaire_reprogramme_un_rendez_vous_a_reprogrammer(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');
        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00');

        $this->actingAs($this->secretaire)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 10:00'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirme')
            ->assertJsonPath('data.date_heure', '2026-11-04 10:00');

        $this->assertSame(StatutRdv::Confirme, $rdv->fresh()->statut);
    }

    public function test_la_reprogrammation_sur_un_creneau_encore_indisponible_est_refusee(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');
        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00');

        $this->actingAs($this->secretaire)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 11:00'])
            ->assertUnprocessable();

        $this->assertSame(StatutRdv::AReprogrammer, $rdv->fresh()->statut);
    }

    public function test_la_secretaire_annule_un_rendez_vous_a_reprogrammer(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');
        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00');

        $this->actingAs($this->secretaire)->postJson("/api/rendez-vous/{$rdv->id}/annulation")
            ->assertOk()
            ->assertJsonPath('data.statut', 'annule');

        $this->assertSame(StatutRdv::Annule, $rdv->fresh()->statut);
    }

    public function test_aucune_route_de_modification_ni_de_suppression_d_une_indisponibilite(): void
    {
        $this->indisponibilite('2026-11-02 09:00', '2026-11-02 12:00')->assertCreated();
        $id = Indisponibilite::firstOrFail()->id;

        $this->actingAs($this->secretaire)->patchJson("/api/medecins/{$this->medecin->id}/indisponibilites/{$id}", [])->assertNotFound();
        $this->actingAs($this->secretaire)->deleteJson("/api/medecins/{$this->medecin->id}/indisponibilites/{$id}")->assertNotFound();
    }
}
