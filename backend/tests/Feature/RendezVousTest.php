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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module 5 : réservation (RG05 à RG07), modification et annulation (RG08), accès (RG10).
 * Date de référence fixe : lundi 2 novembre 2026 à 08:00, fuseau Africa/Dakar.
 */
class RendezVousTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE_409 = 'Ce créneau vient d\'être réservé par quelqu\'un d\'autre. Veuillez choisir un autre créneau.';

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
    private function medecinDisponible(?Specialite $specialite = null): User
    {
        $medecin = User::factory()->medecin($specialite ?? $this->cardiologie)->create();
        foreach ([1, 3] as $jour) {
            Disponibilite::create(['medecin_id' => $medecin->id, 'jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 30]);
        }

        return $medecin;
    }

    private function rdv(string $dateHeure, ?User $patient = null, StatutRdv $statut = StatutRdv::Confirme, ?User $medecin = null): RendezVous
    {
        return RendezVous::create([
            'patient_id' => ($patient ?? $this->patient)->id,
            'medecin_id' => ($medecin ?? $this->medecin)->id,
            'date_heure' => $dateHeure,
            'statut' => $statut->value,
        ]);
    }

    private function reserver(User $patient, string $dateHeure, ?User $medecin = null)
    {
        return $this->actingAs($patient)->postJson('/api/rendez-vous', [
            'medecin_id' => ($medecin ?? $this->medecin)->id,
            'date_heure' => $dateHeure,
        ]);
    }

    // --- Réservation --------------------------------------------------------------------

    public function test_reservation_confirmee_automatiquement(): void
    {
        $this->reserver($this->patient, '2026-11-02 09:00')
            ->assertCreated()
            ->assertJsonPath('data.statut', 'confirme')
            ->assertJsonPath('data.statut_libelle', 'Confirmé')
            ->assertJsonPath('data.date_heure', '2026-11-02 09:00')
            ->assertJsonPath('data.medecin.id', $this->medecin->id)
            ->assertJsonPath('data.specialite.libelle', 'Cardiologie')
            ->assertJsonMissingPath('data.patient');

        $this->assertDatabaseHas('rendez_vous', ['patient_id' => $this->patient->id, 'medecin_id' => $this->medecin->id, 'statut' => 'confirme']);
        $this->assertDatabaseCount('preconsultations', 0);
    }

    public function test_t04_deux_reservations_du_meme_creneau(): void
    {
        $autre = User::factory()->create();

        $this->reserver($this->patient, '2026-11-02 09:00')->assertCreated();
        $this->reserver($autre, '2026-11-02 09:00')
            ->assertStatus(409)
            ->assertExactJson(['message' => self::MESSAGE_409]);

        $this->assertSame(1, RendezVous::where('date_heure', '2026-11-02 09:00')->count());
        $this->assertDatabaseMissing('rendez_vous', ['patient_id' => $autre->id]);
    }

    public function test_t04_violation_de_l_index_unique_renvoie_409(): void
    {
        // Le créneau est pris entre la revérification et l'insertion : seul l'index unique partiel
        // rendez_vous_creneau_actif_unique l'empêche.
        $this->partialMock(CreneauService::class)->shouldReceive('verifier')->andReturnNull();
        $this->rdv('2026-11-02 09:00', User::factory()->create());
        $niveau = DB::transactionLevel();

        $this->reserver($this->patient, '2026-11-02 09:00')
            ->assertStatus(409)
            ->assertJsonPath('message', self::MESSAGE_409);

        // La transaction de la réservation a été annulée.
        $this->assertSame($niveau, DB::transactionLevel());
        $this->assertDatabaseCount('rendez_vous', 1);
    }

    public function test_un_creneau_a_reprogrammer_n_est_pas_reservable_mais_un_annule_l_est(): void
    {
        $this->rdv('2026-11-02 09:00', User::factory()->create(), StatutRdv::AReprogrammer);
        $this->rdv('2026-11-02 09:30', User::factory()->create(), StatutRdv::Annule);

        $this->reserver($this->patient, '2026-11-02 09:00')->assertStatus(409);
        $this->reserver($this->patient, '2026-11-02 09:30')->assertCreated();
    }

    public function test_creneau_hors_disponibilite_passe_ou_indisponible_refuse(): void
    {
        Indisponibilite::create([
            'medecin_id' => $this->medecin->id, 'enregistree_par' => $this->secretaire->id,
            'date_debut' => '2026-11-04 00:00', 'date_fin' => '2026-11-04 23:59',
        ]);

        $cas = [
            '2026-11-02 12:00' => 'Ce créneau ne correspond à aucune disponibilité du médecin.', // fin de plage
            '2026-11-02 09:15' => 'Ce créneau ne correspond à aucune disponibilité du médecin.', // non aligné
            '2026-11-03 09:00' => 'Ce créneau ne correspond à aucune disponibilité du médecin.', // mardi
            '2026-11-04 09:00' => 'Le médecin est indisponible sur ce créneau.',
            '2026-10-26 09:00' => 'Ce créneau est déjà passé.',
            '2026-12-07 09:00' => 'Les rendez-vous se prennent au plus 30 jours à l\'avance.',
        ];

        foreach ($cas as $dateHeure => $message) {
            $this->reserver($this->patient, $dateHeure)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['date_heure' => $message]);
        }

        // Créneau du jour déjà commencé.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 09:10', 'Africa/Dakar'));
        $this->reserver($this->patient, '2026-11-02 09:00')->assertJsonValidationErrors(['date_heure' => 'Ce créneau est déjà passé.']);

        $this->assertDatabaseCount('rendez_vous', 0);
    }

    public function test_validation_de_la_reservation(): void
    {
        $this->actingAs($this->patient)->postJson('/api/rendez-vous', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['medecin_id', 'date_heure']);
        $this->reserver($this->patient, '02/11/2026 09:00')->assertJsonValidationErrors('date_heure');

        $this->actingAs($this->patient)->postJson('/api/rendez-vous', ['medecin_id' => $this->secretaire->id, 'date_heure' => '2026-11-02 09:00'])
            ->assertJsonValidationErrors(['medecin_id' => 'Ce médecin n\'existe pas.']);

        $this->medecin->update(['actif' => false]);
        $this->reserver($this->patient, '2026-11-02 09:00')->assertJsonValidationErrors('medecin_id');

        $this->assertDatabaseCount('rendez_vous', 0);
    }

    public function test_seul_le_patient_reserve(): void
    {
        foreach ([$this->secretaire, $this->medecin, User::factory()->administrateur()->create()] as $acteur) {
            $this->reserver($acteur, '2026-11-02 09:00')->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/rendez-vous', ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 09:00'])->assertUnauthorized();
        $this->getJson('/api/mes-rendez-vous')->assertUnauthorized();

        $this->assertDatabaseCount('rendez_vous', 0);
    }

    // --- Consultation ---------------------------------------------------------------------

    public function test_mes_rendez_vous_ne_contient_que_les_rendez_vous_du_patient(): void
    {
        $ancien = $this->rdv('2026-10-26 09:00', statut: StatutRdv::Passe);
        $prochain = $this->rdv('2026-11-04 10:00');
        $this->rdv('2026-11-02 09:00', User::factory()->create());

        $this->actingAs($this->patient)->getJson('/api/mes-rendez-vous')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $prochain->id)
            ->assertJsonPath('data.0.modifiable', true)
            ->assertJsonPath('data.0.limite_modification', '2026-11-03 10:00')
            ->assertJsonPath('data.1.id', $ancien->id)
            ->assertJsonPath('data.1.statut_libelle', 'Passé')
            ->assertJsonPath('data.1.modifiable', false)
            ->assertJsonPath('data.1.annulable', false);
    }

    // --- Annulation et modification : RG08, T09, T10 --------------------------------------

    public function test_annulation_par_le_patient_dans_le_delai(): void
    {
        $rdv = $this->rdv('2026-11-04 10:00');

        $this->actingAs($this->patient)->postJson("/api/rendez-vous/{$rdv->id}/annulation")
            ->assertOk()
            ->assertJsonPath('data.statut', 'annule')
            ->assertJsonPath('data.statut_libelle', 'Annulé');

        // Le créneau est de nouveau proposé.
        $heures = collect($this->getJson("/api/medecins/{$this->medecin->id}/creneaux?du=2026-11-04&au=2026-11-04")->json('data.jours.0.creneaux'))->pluck('heure');
        $this->assertContains('10:00', $heures);
    }

    public function test_t09_annulation_et_modification_apres_le_delai_refusees(): void
    {
        // Rendez-vous dans 23 heures (délai de 24 heures).
        $rdv = $this->rdv('2026-11-03 07:00');
        $message = 'Un rendez-vous ne peut plus être annulé en ligne moins de 24 heures avant son heure. Veuillez contacter la clinique.';

        $this->actingAs($this->patient)->postJson("/api/rendez-vous/{$rdv->id}/annulation")
            ->assertForbidden()
            ->assertJsonPath('message', $message);

        $this->actingAs($this->patient)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 09:00'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Un rendez-vous ne peut plus être modifié en ligne moins de 24 heures avant son heure. Veuillez contacter la clinique.');

        $this->assertSame(StatutRdv::Confirme, $rdv->fresh()->statut);
        $this->assertSame('2026-11-03 07:00', $rdv->fresh()->date_heure->format('Y-m-d H:i'));

        $this->actingAs($this->patient)->getJson('/api/mes-rendez-vous')
            ->assertJsonPath('data.0.modifiable', false)
            ->assertJsonPath('data.0.annulable', false);
    }

    public function test_le_delai_est_lu_dans_la_configuration_et_sa_borne_est_exclue(): void
    {
        $rdv = $this->rdv('2026-11-03 08:00');

        // Exactement 24 heures avant : refusé.
        $this->actingAs($this->patient)->postJson("/api/rendez-vous/{$rdv->id}/annulation")->assertForbidden();

        config(['rendezvous.delai_modification_heures' => 12]);
        $this->actingAs($this->patient)->postJson("/api/rendez-vous/{$rdv->id}/annulation")->assertOk();
    }

    public function test_la_secretaire_modifie_et_annule_sans_delai(): void
    {
        $rdv = $this->rdv('2026-11-02 09:00');
        $autre = $this->rdv('2026-11-02 10:00', User::factory()->create());

        $this->actingAs($this->secretaire)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-02 11:30'])
            ->assertOk()
            ->assertJsonPath('data.date_heure', '2026-11-02 11:30')
            ->assertJsonPath('data.patient.id', $this->patient->id);

        $this->actingAs($this->secretaire)->postJson("/api/rendez-vous/{$autre->id}/annulation")
            ->assertOk()
            ->assertJsonPath('data.statut', 'annule');
    }

    public function test_t10_acces_au_rendez_vous_d_un_autre_patient(): void
    {
        $rdv = $this->rdv('2026-11-04 10:00');
        $intrus = User::factory()->create();

        $this->actingAs($intrus)->postJson("/api/rendez-vous/{$rdv->id}/annulation")
            ->assertForbidden()
            ->assertJsonPath('message', 'Ce rendez-vous ne vous appartient pas.');
        $this->actingAs($intrus)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 11:00'])
            ->assertForbidden();
        $this->actingAs($intrus)->getJson('/api/mes-rendez-vous')->assertOk()->assertJsonCount(0, 'data');

        // Médecin et administrateur ne gèrent pas les rendez-vous ; la liste du secrétariat leur est fermée.
        foreach ([$this->medecin, User::factory()->administrateur()->create()] as $acteur) {
            $this->actingAs($acteur)->postJson("/api/rendez-vous/{$rdv->id}/annulation")->assertForbidden();
            $this->actingAs($acteur)->getJson('/api/secretariat/rendez-vous')->assertForbidden();
        }
        $this->actingAs($this->patient)->getJson('/api/secretariat/rendez-vous')->assertForbidden();

        $this->assertSame(StatutRdv::Confirme, $rdv->fresh()->statut);
    }

    public function test_un_rendez_vous_annule_ou_passe_ne_se_modifie_plus(): void
    {
        $annule = $this->rdv('2026-11-04 10:00', statut: StatutRdv::Annule);
        $passe = $this->rdv('2026-10-26 10:00', statut: StatutRdv::Passe);
        // Heure dépassée, statut pas encore mis à jour par la tâche planifiée.
        $depasse = $this->rdv('2026-11-02 07:30');

        foreach ([$annule, $passe, $depasse] as $rdv) {
            foreach ([$this->patient, $this->secretaire] as $acteur) {
                $this->actingAs($acteur)->postJson("/api/rendez-vous/{$rdv->id}/annulation")->assertForbidden();
                $this->actingAs($acteur)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 11:00'])->assertForbidden();
            }
        }

        $this->actingAs($this->secretaire)->postJson("/api/rendez-vous/{$annule->id}/annulation")
            ->assertJsonPath('message', 'Ce rendez-vous est « Annulé » : il ne peut plus être annulé.');
    }

    public function test_modification_par_le_patient_meme_medecin_ou_autre_medecin_de_la_specialite(): void
    {
        $rdv = $this->rdv('2026-11-04 10:00');
        $confrere = $this->medecinDisponible();

        $this->actingAs($this->patient)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 11:00'])
            ->assertOk()
            ->assertJsonPath('data.date_heure', '2026-11-04 11:00')
            ->assertJsonPath('data.statut', 'confirme');

        $this->actingAs($this->patient)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $confrere->id, 'date_heure' => '2026-11-04 11:00'])
            ->assertOk()
            ->assertJsonPath('data.medecin.id', $confrere->id);

        // L'ancien créneau est libéré.
        $this->reserver(User::factory()->create(), '2026-11-04 11:00')->assertCreated();
        $this->assertDatabaseCount('rendez_vous', 2);
    }

    public function test_modification_refusee_vers_une_autre_specialite_le_meme_creneau_ou_un_creneau_pris(): void
    {
        $rdv = $this->rdv('2026-11-04 10:00');
        $this->rdv('2026-11-04 11:00', User::factory()->create());
        $orl = $this->medecinDisponible(Specialite::where('libelle', 'ORL')->first());
        $url = "/api/rendez-vous/{$rdv->id}";

        $this->actingAs($this->patient)->patchJson($url, ['medecin_id' => $orl->id, 'date_heure' => '2026-11-04 09:00'])
            ->assertJsonValidationErrors(['medecin_id' => 'Le rendez-vous ne peut être déplacé que vers un médecin de la même spécialité.']);
        $this->actingAs($this->patient)->patchJson($url, ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 10:00'])
            ->assertJsonValidationErrors(['date_heure' => 'Ce rendez-vous est déjà sur ce créneau.']);
        $this->actingAs($this->patient)->patchJson($url, ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 11:00'])
            ->assertStatus(409)
            ->assertJsonPath('message', self::MESSAGE_409);
        $this->actingAs($this->patient)->patchJson($url, ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 12:00'])
            ->assertJsonValidationErrors('date_heure');

        $this->assertSame('2026-11-04 10:00', $rdv->fresh()->date_heure->format('Y-m-d H:i'));
    }

    public function test_un_rendez_vous_a_reprogrammer_deplace_repasse_a_confirme(): void
    {
        $rdv = $this->rdv('2026-11-04 10:00', statut: StatutRdv::AReprogrammer);

        $this->actingAs($this->secretaire)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-09 09:00'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirme');
    }

    // --- Liste du secrétariat -------------------------------------------------------------

    public function test_liste_du_secretariat_avec_filtres(): void
    {
        $confrere = $this->medecinDisponible();
        $a = $this->rdv('2026-11-02 09:00');
        $b = $this->rdv('2026-11-02 10:00', User::factory()->create(), StatutRdv::Annule);
        $c = $this->rdv('2026-11-04 09:00', User::factory()->create(), medecin: $confrere);

        $ids = fn (string $requete) => array_column($this->actingAs($this->secretaire)->getJson('/api/secretariat/rendez-vous'.$requete)->assertOk()->json('data'), 'id');

        $this->assertSame([$a->id, $b->id, $c->id], $ids(''));
        $this->assertSame([$c->id], $ids("?medecin_id={$confrere->id}"));
        $this->assertSame([$a->id, $b->id], $ids('?date=2026-11-02'));
        $this->assertSame([$b->id], $ids('?statut=annule'));
        $this->assertSame([$a->id], $ids("?medecin_id={$this->medecin->id}&date=2026-11-02&statut=confirme"));

        $this->actingAs($this->secretaire)->getJson('/api/secretariat/rendez-vous')
            ->assertJsonPath('data.0.patient.telephone', $this->patient->telephone)
            ->assertJsonPath('data.0.modifiable', true)
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($this->secretaire)->getJson('/api/secretariat/rendez-vous?statut=inconnu&date=2026/11/02&medecin_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statut', 'date', 'medecin_id']);
    }

    public function test_le_rendez_vous_reserve_apparait_dans_le_planning(): void
    {
        $this->reserver($this->patient, '2026-11-02 09:30')->assertCreated();

        $this->actingAs($this->secretaire)->getJson("/api/medecins/{$this->medecin->id}/planning?date=2026-11-02")
            ->assertJsonCount(1, 'data.rendez_vous')
            ->assertJsonPath('data.rendez_vous.0.heure', '09:30')
            ->assertJsonPath('data.rendez_vous.0.statut', 'confirme');
    }

    public function test_les_heures_sont_enregistrees_dans_le_fuseau_de_dakar(): void
    {
        $this->reserver($this->patient, '2026-11-02 09:00')->assertCreated();

        $this->assertSame('2026-11-02 09:00:00', DB::table('rendez_vous')->value('date_heure'));
        $this->actingAs($this->patient)->getJson('/api/mes-rendez-vous')->assertJsonPath('data.0.heure', '09:00');
    }
}
