<?php

namespace Tests\Feature;

use App\Enums\StatutRdv;
use App\Models\Disponibilite;
use App\Models\Notification as Journal;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use App\Notifications\AnnulationRendezVous;
use App\Notifications\ConfirmationRendezVous;
use App\Notifications\ModificationRendezVous;
use App\Notifications\NotificationRendezVous;
use App\Notifications\RendezVousAReprogrammer;
use Carbon\CarbonImmutable;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Module 8 : courriels au patient (section 9), via la file d'attente et après validation des
 * transactions, et journal dans la table notifications.
 * Date de référence fixe : lundi 2 novembre 2026 à 08:00, fuseau Africa/Dakar.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const MOTIF = 'Motif-confidentiel-xyz';

    private const SYMPTOMES = 'Symptomes-confidentiels-abc';

    private Specialite $cardiologie;

    private User $secretaire;

    private User $medecin;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Dakar'));
        config([
            'clinique.adresse' => 'Adresse fictive',
            'clinique.telephone' => '00 000 00 01',
            'clinique.email' => 'contact@exemple.test',
        ]);

        $this->seed(SpecialiteSeeder::class);
        $this->cardiologie = Specialite::where('libelle', 'Cardiologie')->firstOrFail();
        $this->secretaire = User::factory()->secretaire()->create();
        $this->medecin = User::factory()->medecin($this->cardiologie)->create(['nom' => 'Fictif', 'prenom' => 'Docteur']);
        foreach ([1, 3] as $jour) {
            Disponibilite::create(['medecin_id' => $this->medecin->id, 'jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 30]);
        }
        $this->patient = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function rdv(string $dateHeure, StatutRdv $statut = StatutRdv::Confirme): RendezVous
    {
        return RendezVous::create(['patient_id' => $this->patient->id, 'medecin_id' => $this->medecin->id, 'date_heure' => $dateHeure, 'statut' => $statut->value]);
    }

    private function reserver(?User $patient = null, string $dateHeure = '2026-11-04 10:00', ?array $preconsultation = null)
    {
        return $this->actingAs($patient ?? $this->patient)->postJson('/api/rendez-vous', array_filter([
            'medecin_id' => $this->medecin->id,
            'date_heure' => $dateHeure,
            'preconsultation' => $preconsultation,
        ]));
    }

    /** @return list<Email> courriels réellement envoyés (transport « array », file « sync ») */
    private function courriels(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->map->getOriginalMessage()->values()->all();
    }

    public function test_un_courriel_de_confirmation_a_la_reservation(): void
    {
        Notification::fake();

        $id = $this->reserver()->assertCreated()->json('data.id');

        Notification::assertSentTo($this->patient, ConfirmationRendezVous::class, fn (ConfirmationRendezVous $n, array $canaux) => $n->rendezVousId === $id && $canaux === ['mail']);
        Notification::assertCount(1);
        $this->assertDatabaseHas('notifications', ['rendez_vous_id' => $id, 'objet' => 'Confirmation de votre rendez-vous']);
    }

    public function test_un_courriel_de_modification_par_le_patient(): void
    {
        Notification::fake();
        $rdv = $this->rdv('2026-11-04 09:00');

        $this->actingAs($this->patient)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-04 11:00'])->assertOk();

        Notification::assertSentTo($this->patient, ModificationRendezVous::class, function (ModificationRendezVous $n) use ($rdv) {
            return $n->rendezVousId === $rdv->id && ! $n->reprogrammation && $n->objet === 'Modification de votre rendez-vous'
                && str_contains($n->contenu(), 'mercredi 4 novembre 2026 à 09:00 a été modifié')
                && str_contains($n->contenu(), 'Heure : 11:00');
        });
        Notification::assertCount(1);
    }

    public function test_un_courriel_de_reprogrammation_par_la_secretaire(): void
    {
        Notification::fake();
        $rdv = $this->rdv('2026-11-04 09:00', StatutRdv::AReprogrammer);

        $this->actingAs($this->secretaire)->patchJson("/api/rendez-vous/{$rdv->id}", ['medecin_id' => $this->medecin->id, 'date_heure' => '2026-11-09 10:00'])->assertOk();

        Notification::assertSentTo($this->patient, ModificationRendezVous::class, fn (ModificationRendezVous $n) => $n->reprogrammation
            && $n->objet === 'Reprogrammation de votre rendez-vous'
            && str_contains($n->contenu(), 'reprogrammé par la clinique'));
        Notification::assertCount(1);
        $this->assertDatabaseHas('notifications', ['rendez_vous_id' => $rdv->id, 'objet' => 'Reprogrammation de votre rendez-vous']);
    }

    public function test_un_courriel_d_annulation_par_le_patient(): void
    {
        Notification::fake();
        $rdv = $this->rdv('2026-11-04 09:00');

        $this->actingAs($this->patient)->postJson("/api/rendez-vous/{$rdv->id}/annulation")->assertOk();

        Notification::assertSentTo($this->patient, AnnulationRendezVous::class, fn (AnnulationRendezVous $n) => $n->parPatient
            && str_contains($n->contenu(), 'Nous vous confirmons l\'annulation'));
        Notification::assertCount(1);
    }

    public function test_un_courriel_d_annulation_par_la_secretaire(): void
    {
        Notification::fake();
        $rdv = $this->rdv('2026-11-04 09:00', StatutRdv::AReprogrammer);

        $this->actingAs($this->secretaire)->postJson("/api/rendez-vous/{$rdv->id}/annulation")->assertOk();

        // Le courriel va au patient, jamais à la secrétaire.
        Notification::assertSentTo($this->patient, AnnulationRendezVous::class, fn (AnnulationRendezVous $n) => ! $n->parPatient
            && str_contains($n->contenu(), 'La clinique a annulé votre rendez-vous'));
        Notification::assertNotSentTo($this->secretaire, AnnulationRendezVous::class);
        Notification::assertCount(1);
    }

    public function test_un_courriel_au_passage_a_reprogrammer(): void
    {
        Notification::fake();
        $rdv = $this->rdv('2026-11-04 09:00');

        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$this->medecin->id}/indisponibilites", ['date_debut' => '2026-11-04 08:00', 'date_fin' => '2026-11-04 12:00'])->assertCreated();

        Notification::assertSentTo($this->patient, RendezVousAReprogrammer::class, fn (RendezVousAReprogrammer $n) => $n->rendezVousId === $rdv->id
            && $n->objet === 'Votre rendez-vous doit être reprogrammé');
        Notification::assertCount(1);
    }

    public function test_les_courriels_passent_par_la_file_d_attente_apres_validation_de_la_transaction(): void
    {
        Queue::fake();

        $this->reserver()->assertCreated();

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) => $job->notification instanceof ConfirmationRendezVous
            && $job->notification->afterCommit === true
            && $job->channels === ['mail']);
        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_le_courriel_contient_le_rendez_vous_et_la_clinique_sans_preconsultation(): void
    {
        $id = $this->reserver(preconsultation: ['motif' => self::MOTIF, 'symptomes' => self::SYMPTOMES])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('preconsultations', ['rendez_vous_id' => $id, 'motif' => self::MOTIF]);

        $courriels = $this->courriels();
        $this->assertCount(1, $courriels);
        $courriel = $courriels[0];

        $this->assertSame($this->patient->email, $courriel->getTo()[0]->getAddress());
        $this->assertSame('Confirmation de votre rendez-vous', $courriel->getSubject());

        foreach ([$courriel->getTextBody(), html_entity_decode($courriel->getHtmlBody(), ENT_QUOTES)] as $corps) {
            foreach (['mercredi 4 novembre 2026', '10:00', 'Docteur Fictif', 'Cardiologie', 'Adresse fictive', '00 000 00 01', 'contact@exemple.test', 'Bonjour'] as $attendu) {
                $this->assertStringContainsString($attendu, $corps);
            }
            // Section 9 : aucune donnée de préconsultation.
            $this->assertStringNotContainsString(self::MOTIF, $corps);
            $this->assertStringNotContainsString(self::SYMPTOMES, $corps);
            $this->assertStringNotContainsString('Hello', $corps);
        }

        $journal = Journal::where('rendez_vous_id', $id)->sole();
        $this->assertStringNotContainsString(self::MOTIF, $journal->contenu);
        $this->assertStringNotContainsString(self::SYMPTOMES, $journal->contenu);
    }

    public function test_le_journal_renseigne_date_envoi_quand_l_envoi_a_reussi(): void
    {
        $id = $this->reserver()->assertCreated()->json('data.id');

        $journal = Journal::where('rendez_vous_id', $id)->sole();
        $this->assertSame('Confirmation de votre rendez-vous', $journal->objet);
        $this->assertStringContainsString('Date : mercredi 4 novembre 2026', $journal->contenu);
        $this->assertStringContainsString('Spécialité : Cardiologie', $journal->contenu);
        $this->assertNotNull($journal->date_envoi);
        $this->assertTrue($journal->date_envoi->equalTo(CarbonImmutable::now()));
    }

    public function test_sans_envoi_effectif_date_envoi_reste_vide(): void
    {
        // Envoi intercepté : la ligne du journal existe, mais aucun courriel n'est parti.
        Notification::fake();

        $id = $this->reserver()->assertCreated()->json('data.id');

        $this->assertNull(Journal::where('rendez_vous_id', $id)->sole()->date_envoi);
    }

    public function test_aucun_courriel_si_la_transaction_echoue(): void
    {
        Notification::fake();
        $this->rdv('2026-11-04 10:00');

        // 409 : créneau déjà réservé (RG07).
        $this->reserver(User::factory()->create())->assertStatus(409);

        // 422 : indisponibilité invalide.
        $this->actingAs($this->secretaire)->postJson("/api/medecins/{$this->medecin->id}/indisponibilites", ['date_debut' => '2026-11-04 12:00', 'date_fin' => '2026-11-04 08:00'])
            ->assertUnprocessable();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_une_classe_par_evenement_canal_mail_et_file_d_attente(): void
    {
        $rdv = $this->rdv('2026-11-04 09:00');

        foreach ([
            new ConfirmationRendezVous($rdv),
            new ModificationRendezVous($rdv, CarbonImmutable::parse('2026-11-02 09:00')),
            new AnnulationRendezVous($rdv, parPatient: true),
            new RendezVousAReprogrammer($rdv),
        ] as $notification) {
            $this->assertInstanceOf(NotificationRendezVous::class, $notification);
            $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
            $this->assertSame(['mail'], $notification->via($this->patient));
            $this->assertTrue($notification->afterCommit);
        }
    }
}
