<?php

namespace Tests\Feature;

use App\Enums\StatutRdv;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 10 : tâche planifiée rendezvous:marquer-passes (section 8.5, T12).
 * Date de référence fixe : mardi 20 octobre 2026 à 10:00, fuseau Africa/Dakar.
 */
class TachePlanifieeTest extends TestCase
{
    use RefreshDatabase;

    private User $medecin;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Africa/Dakar'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 10:00', 'Africa/Dakar'));

        $this->seed(SpecialiteSeeder::class);
        $this->medecin = User::factory()->medecin(Specialite::where('libelle', 'Cardiologie')->firstOrFail())->create();
        $this->patient = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function rdv(string $dateHeure, StatutRdv $statut = StatutRdv::Confirme): RendezVous
    {
        return RendezVous::create([
            'patient_id' => $this->patient->id,
            'medecin_id' => $this->medecin->id,
            'date_heure' => $dateHeure,
            'statut' => $statut->value,
        ]);
    }

    public function test_t12_marquer_passes_ne_touche_que_les_rendez_vous_confirmes_depasses(): void
    {
        Notification::fake();

        $hier = $this->rdv('2026-10-19 09:00');
        $ceMatin = $this->rdv('2026-10-20 09:30');
        $maintenant = $this->rdv('2026-10-20 10:00');
        $plusTard = $this->rdv('2026-10-20 10:30');
        $demain = $this->rdv('2026-10-21 09:00');
        $aReprogrammerDepasse = $this->rdv('2026-10-19 11:00', StatutRdv::AReprogrammer);
        $annuleDepasse = $this->rdv('2026-10-19 11:30', StatutRdv::Annule);
        $dejaPasse = $this->rdv('2026-10-12 09:00', StatutRdv::Passe);

        $this->artisan('rendezvous:marquer-passes')
            ->expectsOutput('3 rendez-vous marqué(s) comme passé(s).')
            ->assertSuccessful();

        $statut = fn (RendezVous $r) => $r->fresh()->statut;

        // Rendez-vous confirmés dépassés (l'heure exacte comprise, comme RendezVousPolicy).
        $this->assertSame(StatutRdv::Passe, $statut($hier));
        $this->assertSame(StatutRdv::Passe, $statut($ceMatin));
        $this->assertSame(StatutRdv::Passe, $statut($maintenant));

        // Rendez-vous futurs et autres statuts inchangés.
        $this->assertSame(StatutRdv::Confirme, $statut($plusTard));
        $this->assertSame(StatutRdv::Confirme, $statut($demain));
        $this->assertSame(StatutRdv::AReprogrammer, $statut($aReprogrammerDepasse));
        $this->assertSame(StatutRdv::Annule, $statut($annuleDepasse));
        $this->assertSame(StatutRdv::Passe, $statut($dejaPasse));

        // Aucun courriel : le passage à « passé » n'est pas un des quatre événements (section 9).
        Notification::assertNothingSent();
    }

    public function test_t12_execution_repetee_sans_effet_supplementaire(): void
    {
        $this->rdv('2026-10-19 09:00');

        $this->artisan('rendezvous:marquer-passes')->expectsOutput('1 rendez-vous marqué(s) comme passé(s).');
        $this->artisan('rendezvous:marquer-passes')->expectsOutput('0 rendez-vous marqué(s) comme passé(s).');

        // Une heure plus tard, le rendez-vous de 10:30 est à son tour dépassé.
        $this->rdv('2026-10-20 10:30');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 11:00', 'Africa/Dakar'));
        $this->artisan('rendezvous:marquer-passes')->expectsOutput('1 rendez-vous marqué(s) comme passé(s).');
    }

    public function test_commande_planifiee_toutes_les_heures(): void
    {
        $evenements = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'rendezvous:marquer-passes'));

        $this->assertCount(1, $evenements);
        $this->assertSame('0 * * * *', $evenements->first()->expression);
    }
}
