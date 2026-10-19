<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Disponibilite;
use App\Models\Indisponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Database\Seeders\AdministrateurSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Module 1 : schéma, contraintes, seeders, authentification de base et middleware de rôle.
 */
class SocleTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_les_dix_specialites_avec_les_jours_de_presence(): void
    {
        $this->seed(SpecialiteSeeder::class);

        $this->assertSame(10, Specialite::count());
        $this->assertSame('Jours de présence : Mardi', Specialite::where('libelle', 'Ophtalmologie')->value('description'));
    }

    public function test_le_seeder_administrateur_lit_les_identifiants_de_la_configuration(): void
    {
        config(['auth.administrateur_initial' => ['email' => 'admin@exemple.test', 'password' => 'secret-de-test']]);

        $this->seed(AdministrateurSeeder::class);

        $admin = User::where('email', 'admin@exemple.test')->firstOrFail();
        $this->assertSame(Role::Administrateur, $admin->role);
        $this->assertNotSame('secret-de-test', $admin->getAuthPassword());
        $this->assertTrue(password_verify('secret-de-test', $admin->getAuthPassword()));
    }

    public function test_le_seeder_de_demonstration_cree_des_donnees_fictives_coherentes(): void
    {
        config(['auth.demo_password' => 'demo-de-test']);

        $this->seed(DemoSeeder::class);

        $this->assertSame(10, Medecin::count());
        $this->assertSame(1, User::secretaires()->count());
        $this->assertSame(3, User::patients()->count());
        $this->assertGreaterThan(0, RendezVous::count());

        $ophtalmo = Medecin::whereHas('specialite', fn ($q) => $q->where('libelle', 'Ophtalmologie'))->firstOrFail();
        $this->assertSame([2], $ophtalmo->disponibilites()->pluck('jour_semaine')->all());
        $this->assertStringContainsString('Démo', $ophtalmo->user->nomComplet());
    }

    public function test_la_contrainte_check_refuse_un_role_inconnu(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert(['nom' => 'X', 'prenom' => 'Y', 'email' => 'x@exemple.test', 'password' => 'x', 'role' => 'invite']);
    }

    public function test_les_contraintes_check_des_disponibilites(): void
    {
        $medecin = User::factory()->medecin()->create();

        foreach ([
            ['jour_semaine' => 8, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 30],
            ['jour_semaine' => 1, 'heure_debut' => '12:00', 'heure_fin' => '09:00', 'duree_creneau' => 30],
            ['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'duree_creneau' => 0],
        ] as $valeurs) {
            try {
                DB::transaction(fn () => Disponibilite::create(['medecin_id' => $medecin->id] + $valeurs));
                $this->fail('La contrainte CHECK aurait dû refuser : '.json_encode($valeurs));
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->getCode());
            }
        }
    }

    public function test_la_contrainte_check_des_indisponibilites(): void
    {
        $medecin = User::factory()->medecin()->create();
        $secretaire = User::factory()->secretaire()->create();

        $this->expectException(QueryException::class);

        Indisponibilite::create([
            'medecin_id' => $medecin->id,
            'enregistree_par' => $secretaire->id,
            'date_debut' => '2030-01-02 10:00',
            'date_fin' => '2030-01-01 10:00',
        ]);
    }

    public function test_l_index_unique_partiel_n_autorise_qu_un_rendez_vous_actif_par_creneau(): void
    {
        $medecin = User::factory()->medecin()->create();
        [$p1, $p2, $p3] = User::factory()->count(3)->create();
        $creneau = ['medecin_id' => $medecin->id, 'date_heure' => '2030-01-07 09:00:00'];

        // Un rendez-vous annulé libère le créneau.
        RendezVous::create($creneau + ['patient_id' => $p1->id, 'statut' => 'annule']);
        RendezVous::create($creneau + ['patient_id' => $p2->id]);

        try {
            DB::transaction(fn () => RendezVous::create($creneau + ['patient_id' => $p3->id, 'statut' => 'a_reprogrammer']));
            $this->fail('L\'index unique partiel aurait dû refuser le second rendez-vous actif.');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->getCode());
        }

        $this->assertSame('confirme', RendezVous::where('patient_id', $p2->id)->first()->statut->value);
    }

    public function test_api_moi_exige_une_authentification(): void
    {
        $this->getJson('/api/moi')->assertUnauthorized();
    }

    public function test_api_moi_renvoie_l_utilisateur_connecte_sans_mot_de_passe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/moi')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'patient')
            ->assertJsonMissingPath('data.password');
    }

    public function test_un_compte_desactive_apres_connexion_est_deconnecte(): void
    {
        $user = User::factory()->inactif()->create();

        $this->actingAs($user)->getJson('/api/moi')->assertUnauthorized();
    }

    public function test_le_middleware_de_role_refuse_les_autres_roles(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'role:secretaire'])->get('/api/_test-role', fn () => 'ok');

        $this->actingAs(User::factory()->create())->getJson('/api/_test-role')->assertForbidden();
        $this->actingAs(User::factory()->secretaire()->create())->getJson('/api/_test-role')->assertOk();
    }

    public function test_le_fuseau_horaire_est_africa_dakar(): void
    {
        $this->assertSame('Africa/Dakar', config('app.timezone'));
        $this->assertSame('Africa/Dakar', date_default_timezone_get());
    }
}
