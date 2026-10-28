<?php

namespace Tests\Feature;

use App\Models\Disponibilite;
use App\Models\Preconsultation;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use App\Services\OrientationService;
use Carbon\CarbonImmutable;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 6 : préconsultation facultative (RG02, RG11), orientation (section 8.3, RG04) et
 * confidentialité de la préconsultation (RG10). Les termes d'orientation utilisés sont ceux,
 * d'exemple, de config/orientation.php.
 * Date de référence fixe : lundi 2 novembre 2026 à 08:00, fuseau Africa/Dakar.
 */
class PreconsultationTest extends TestCase
{
    use RefreshDatabase;

    private User $cardiologue;

    private User $orl;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Dakar'));

        $this->seed(SpecialiteSeeder::class);
        $this->cardiologue = $this->medecinDisponible('Cardiologie');
        $this->orl = $this->medecinDisponible('ORL');
        $this->patient = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function specialite(string $libelle): Specialite
    {
        return Specialite::where('libelle', $libelle)->firstOrFail();
    }

    /** Médecin disponible le lundi et le mercredi de 09:00 à 12:00 (créneaux de 30 min). */
    private function medecinDisponible(string $specialite): User
    {
        $medecin = User::factory()->medecin($this->specialite($specialite))->create();
        foreach ([1, 3] as $jour) {
            Disponibilite::create(['medecin_id' => $medecin->id, 'jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 30]);
        }

        return $medecin;
    }

    private function verifier(string $specialite, string $motif, string $symptomes)
    {
        return $this->actingAs($this->patient)->postJson('/api/orientation/verification', [
            'specialite_id' => $this->specialite($specialite)->id,
            'motif' => $motif,
            'symptomes' => $symptomes,
        ]);
    }

    private function reserver(User $medecin, ?array $preconsultation, string $dateHeure = '2026-11-02 09:00')
    {
        return $this->actingAs($this->patient)->postJson('/api/rendez-vous', array_filter([
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure,
            // Consentement donné par défaut ; une clé fournie par le test l'emporte.
            'preconsultation' => $preconsultation === null ? null : $preconsultation + ['consentement' => true],
        ], fn ($v) => $v !== null));
    }

    private function rdvAvecPreconsultation(): RendezVous
    {
        $rendezVous = RendezVous::create([
            'patient_id' => $this->patient->id,
            'medecin_id' => $this->cardiologue->id,
            'date_heure' => '2026-11-04 09:00',
            'statut' => 'confirme',
        ]);
        $rendezVous->preconsultation()->create(['motif' => 'Contrôle', 'symptomes' => 'Palpitations à l\'effort', 'date_saisie' => now()]);

        return $rendezVous;
    }

    // --- RG02 : préconsultation facultative ----------------------------------------------

    public function test_reservation_sans_preconsultation_toujours_possible(): void
    {
        $this->reserver($this->cardiologue, null)->assertCreated();

        $this->actingAs($this->patient)->postJson('/api/rendez-vous', [
            'medecin_id' => $this->cardiologue->id,
            'date_heure' => '2026-11-02 09:30',
            'preconsultation' => null,
        ])->assertCreated();

        $this->assertDatabaseCount('rendez_vous', 2);
        $this->assertDatabaseCount('preconsultations', 0);
    }

    public function test_reservation_avec_preconsultation_enregistree_sans_l_exposer(): void
    {
        $reponse = $this->reserver($this->cardiologue, ['motif' => 'Contrôle', 'symptomes' => 'Palpitations le soir'])
            ->assertCreated()
            ->assertJsonMissingPath('data.preconsultation')
            ->assertJsonMissingPath('data.motif')
            ->assertJsonMissingPath('data.symptomes');

        $this->assertDatabaseHas('preconsultations', [
            'rendez_vous_id' => $reponse->json('data.id'),
            'motif' => 'Contrôle',
            'symptomes' => 'Palpitations le soir',
            'specialite_suggeree_id' => null,
        ]);
    }

    public function test_preconsultation_exige_le_consentement_et_l_enregistre(): void
    {
        $saisie = ['motif' => 'Contrôle', 'symptomes' => 'Palpitations le soir'];
        $message = 'Vous devez consentir au traitement de vos données de santé pour transmettre la préconsultation.';

        $this->actingAs($this->patient)->postJson('/api/rendez-vous', [
            'medecin_id' => $this->cardiologue->id,
            'date_heure' => '2026-11-02 09:00',
            'preconsultation' => $saisie,
        ])->assertUnprocessable()->assertJsonValidationErrors(['preconsultation.consentement' => $message]);
        $this->reserver($this->cardiologue, $saisie + ['consentement' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['preconsultation.consentement' => $message]);
        $this->assertDatabaseCount('rendez_vous', 0);

        $this->reserver($this->cardiologue, $saisie)->assertCreated();
        $this->assertEquals(CarbonImmutable::now(), Preconsultation::sole()->date_consentement);
    }

    // --- T05 : motif et symptômes obligatoires -----------------------------------------------

    public function test_t05_preconsultation_sans_motif_ou_sans_symptomes(): void
    {
        $this->reserver($this->cardiologue, ['symptomes' => 'Palpitations'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preconsultation.motif' => 'Le motif est obligatoire pour la préconsultation.'])
            ->assertJsonMissingValidationErrors('preconsultation.symptomes');

        $this->reserver($this->cardiologue, ['motif' => 'Contrôle', 'symptomes' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preconsultation.symptomes' => 'Les symptômes sont obligatoires pour la préconsultation.']);

        $this->verifier('Cardiologie', '', 'Palpitations')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motif');

        $this->assertDatabaseCount('rendez_vous', 0);
        $this->assertDatabaseCount('preconsultations', 0);
    }

    public function test_collecte_minimale_aucun_autre_champ_accepte(): void
    {
        // Ni donnée supplémentaire, ni spécialité suggérée fournie par le client.
        $this->reserver($this->cardiologue, ['motif' => 'Contrôle', 'symptomes' => 'Palpitations', 'antecedents' => 'Aucun'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preconsultation');

        $this->reserver($this->cardiologue, ['motif' => 'Contrôle', 'symptomes' => 'Palpitations', 'specialite_suggeree_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preconsultation');

        $this->assertDatabaseCount('rendez_vous', 0);
    }

    // --- T06 et T07 : orientation ----------------------------------------------------------

    public function test_t06_motif_d_une_autre_specialite_suggestion_avec_creneaux(): void
    {
        $orl = $this->specialite('ORL');

        $this->verifier('Cardiologie', 'Douleur à l\'oreille', 'Depuis trois jours')
            ->assertOk()
            ->assertJsonPath('data.suggestion.specialite.id', $orl->id)
            ->assertJsonPath('data.suggestion.specialite.libelle', 'ORL')
            ->assertJsonCount(3, 'data.suggestion.creneaux')
            ->assertJsonPath('data.suggestion.creneaux.0.date_heure', '2026-11-02 09:00')
            ->assertJsonPath('data.suggestion.creneaux.0.medecin.id', $this->orl->id)
            ->assertJsonPath('data.suggestion.creneaux.2.date_heure', '2026-11-02 10:00');

        // Rien n'est enregistré par la vérification.
        $this->assertDatabaseCount('preconsultations', 0);

        // Le patient conserve sa spécialité : réservation possible, suggestion enregistrée.
        $reponse = $this->reserver($this->cardiologue, ['motif' => 'Douleur à l\'oreille', 'symptomes' => 'Depuis trois jours'])
            ->assertCreated()
            ->assertJsonPath('data.specialite.libelle', 'Cardiologie');

        $this->assertDatabaseHas('preconsultations', [
            'rendez_vous_id' => $reponse->json('data.id'),
            'specialite_suggeree_id' => $orl->id,
        ]);
    }

    public function test_t06_le_patient_choisit_la_specialite_suggeree(): void
    {
        $reponse = $this->reserver($this->orl, ['motif' => 'Douleur à l\'oreille', 'symptomes' => 'Depuis trois jours'])
            ->assertCreated()
            ->assertJsonPath('data.specialite.libelle', 'ORL');

        // Plus d'incompatibilité : aucune suggestion enregistrée (décision de l'auteur).
        $this->assertDatabaseHas('preconsultations', ['rendez_vous_id' => $reponse->json('data.id'), 'specialite_suggeree_id' => null]);
    }

    public function test_t07_motif_compatible_aucune_suggestion(): void
    {
        $this->verifier('Cardiologie', 'Palpitations', 'Le soir, au repos')
            ->assertOk()
            ->assertExactJson(['data' => ['suggestion' => null]]);

        // La spécialité choisie fait partie des correspondances, même si une autre en a aussi.
        $this->verifier('Cardiologie', 'Palpitations', 'Et une douleur à l\'oreille')
            ->assertOk()
            ->assertJsonPath('data.suggestion', null);
    }

    public function test_aucune_suggestion_si_aucune_regle_ne_s_applique(): void
    {
        $this->verifier('Cardiologie', 'Contrôle de routine', 'Aucun symptôme particulier')
            ->assertOk()
            ->assertJsonPath('data.suggestion', null);
    }

    public function test_la_specialite_qui_a_le_plus_de_correspondances_est_suggeree(): void
    {
        $this->verifier('Cardiologie', 'Eczéma', 'Peau sèche, et l\'oreille gratte')
            ->assertOk()
            ->assertJsonPath('data.suggestion.specialite.libelle', 'Dermatologie');
    }

    public function test_egalite_departagee_par_l_ordre_de_la_configuration(): void
    {
        // ORL (« oreille ») et Dermatologie (« peau ») : un terme chacun ; ORL est listée avant.
        $this->verifier('Cardiologie', 'Peau irritée', 'Derrière l\'oreille')
            ->assertOk()
            ->assertJsonPath('data.suggestion.specialite.libelle', 'ORL');
    }

    public function test_suggestion_sans_medecin_disponible_renvoie_une_liste_vide(): void
    {
        $this->verifier('Cardiologie', 'Vue qui baisse', 'Depuis un mois')
            ->assertOk()
            ->assertJsonPath('data.suggestion.specialite.libelle', 'Ophtalmologie')
            ->assertJsonCount(0, 'data.suggestion.creneaux');
    }

    // --- Normalisation ----------------------------------------------------------------------

    public function test_normalisation_minuscules_accents_et_ponctuation(): void
    {
        $this->assertSame('mal a l oeil tres fort', OrientationService::normaliser("Mal à l'ŒIL, très   fort !"));
        $this->assertSame('eczema demangeaisons', OrientationService::normaliser('ECZÉMA ; démangeaisons...'));

        $orientation = app(OrientationService::class);
        $this->assertSame(['Dermatologie' => 1], $orientation->correspondances('Un ECZÉMA !'));
        $this->assertSame(['ORL' => 1], $orientation->correspondances('Mal aux OREILLES'));
        $this->assertSame(['Ophtalmologie' => 1], $orientation->correspondances('Mal à l\'Œil'));
        // Un terme doit commencer un mot : « vue » n'est pas trouvé dans « prévue ».
        $this->assertSame([], $orientation->correspondances('Consultation prévue la semaine dernière'));
    }

    // --- T08 : psychiatrie, saisie restreinte (RG11) ---------------------------------------

    public function test_t08_psychiatrie_plus_de_500_caracteres(): void
    {
        $psychiatre = $this->medecinDisponible('Psychiatrie');

        $this->reserver($psychiatre, ['motif' => 'Anxiété', 'symptomes' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preconsultation.symptomes' => 'Les symptômes ne doivent pas dépasser 500 caractères.']);

        $this->verifier('Psychiatrie', 'Anxiété', str_repeat('a', 501))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('symptomes');

        $this->assertDatabaseCount('rendez_vous', 0);

        // 500 caractères (accentués compris) : accepté.
        $this->reserver($psychiatre, ['motif' => 'Anxiété', 'symptomes' => str_repeat('é', 500)])->assertCreated();
    }

    public function test_limite_de_2000_caracteres_hors_psychiatrie(): void
    {
        $this->reserver($this->cardiologue, ['motif' => 'Contrôle', 'symptomes' => str_repeat('a', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preconsultation.symptomes' => 'Les symptômes ne doivent pas dépasser 2000 caractères.']);

        $this->reserver($this->cardiologue, ['motif' => 'Contrôle', 'symptomes' => str_repeat('a', 2000)])->assertCreated();
    }

    public function test_verification_reservee_au_patient(): void
    {
        $this->postJson('/api/orientation/verification', [])->assertUnauthorized();

        $this->actingAs(User::factory()->secretaire()->create())
            ->postJson('/api/orientation/verification', ['specialite_id' => $this->specialite('ORL')->id, 'motif' => 'a', 'symptomes' => 'b'])
            ->assertForbidden();
    }

    // --- T10 : confidentialité (RG10) -------------------------------------------------------

    public function test_t10_preconsultation_lisible_par_le_medecin_du_rendez_vous_et_le_patient(): void
    {
        $rendezVous = $this->rdvAvecPreconsultation();
        $url = "/api/rendez-vous/{$rendezVous->id}/preconsultation";

        foreach ([$this->cardiologue, $this->patient] as $acteur) {
            $this->actingAs($acteur)->getJson($url)
                ->assertOk()
                ->assertJsonPath('data.motif', 'Contrôle')
                ->assertJsonPath('data.symptomes', 'Palpitations à l\'effort')
                ->assertJsonPath('data.specialite_suggeree', null);
        }
    }

    public function test_t10_preconsultation_refusee_aux_autres(): void
    {
        $rendezVous = $this->rdvAvecPreconsultation();
        $url = "/api/rendez-vous/{$rendezVous->id}/preconsultation";

        $autres = [
            $this->orl, // médecin d'un autre rendez-vous
            User::factory()->secretaire()->create(),
            User::factory()->administrateur()->create(),
            User::factory()->create(), // autre patient
        ];

        foreach ($autres as $acteur) {
            $this->actingAs($acteur)->getJson($url)
                ->assertForbidden()
                ->assertJsonMissingPath('data');
        }

        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_rendez_vous_sans_preconsultation_renvoie_404(): void
    {
        $rendezVous = RendezVous::create([
            'patient_id' => $this->patient->id,
            'medecin_id' => $this->cardiologue->id,
            'date_heure' => '2026-11-04 09:00',
            'statut' => 'confirme',
        ]);

        $this->actingAs($this->cardiologue)->getJson("/api/rendez-vous/{$rendezVous->id}/preconsultation")->assertNotFound();
        // Un autre médecin n'apprend pas qu'il n'y en a pas.
        $this->actingAs($this->orl)->getJson("/api/rendez-vous/{$rendezVous->id}/preconsultation")->assertForbidden();
    }

    public function test_la_secretaire_voit_seulement_l_existence_d_une_preconsultation(): void
    {
        $avec = $this->rdvAvecPreconsultation();
        $sans = RendezVous::create([
            'patient_id' => $this->patient->id,
            'medecin_id' => $this->cardiologue->id,
            'date_heure' => '2026-11-04 09:30',
            'statut' => 'confirme',
        ]);
        $secretaire = User::factory()->secretaire()->create();

        $reponse = $this->actingAs($secretaire)->getJson('/api/secretariat/rendez-vous')->assertOk();
        $lignes = collect($reponse->json('data'))->keyBy('id');
        $this->assertTrue($lignes[$avec->id]['a_preconsultation']);
        $this->assertFalse($lignes[$sans->id]['a_preconsultation']);
        $this->assertStringNotContainsString('Palpitations', $reponse->getContent());

        // Planning (secrétaire et médecin) : l'indicateur seulement.
        foreach ([[$secretaire, "/api/medecins/{$this->cardiologue->id}/planning"], [$this->cardiologue, '/api/medecin/planning']] as [$acteur, $url]) {
            $planning = $this->actingAs($acteur)->getJson($url.'?date=2026-11-04')->assertOk();
            $this->assertEqualsCanonicalizing([true, false], array_column($planning->json('data.rendez_vous'), 'a_preconsultation'));
            $this->assertStringNotContainsString('Palpitations', $planning->getContent());
        }
    }

    public function test_la_modification_d_un_rendez_vous_ne_touche_pas_la_preconsultation(): void
    {
        $rendezVous = $this->rdvAvecPreconsultation();

        $this->actingAs($this->patient)->patchJson("/api/rendez-vous/{$rendezVous->id}", [
            'medecin_id' => $this->cardiologue->id,
            'date_heure' => '2026-11-04 10:00',
            'preconsultation' => ['motif' => 'Autre', 'symptomes' => 'Autre'],
        ])->assertOk();

        $this->assertSame('Contrôle', Preconsultation::sole()->motif);
    }
}
