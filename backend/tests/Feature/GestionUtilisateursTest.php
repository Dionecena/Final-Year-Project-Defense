<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Medecin;
use App\Models\Specialite;
use App\Models\User;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 2 : gestion des comptes et des rôles par l'administrateur.
 */
class GestionUtilisateursTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialiteSeeder::class);
        $this->admin = User::factory()->administrateur()->create();
    }

    private function donneesCompte(array $surcharge = []): array
    {
        return $surcharge + [
            'nom' => 'Démo',
            'prenom' => 'Personnel',
            'email' => 'personnel@exemple.test',
            'telephone' => '00 000 00 02',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
            'role' => 'secretaire',
        ];
    }

    public function test_seul_l_administrateur_accede_a_la_gestion_des_comptes(): void
    {
        foreach ([User::factory()->create(), User::factory()->secretaire()->create(), User::factory()->medecin()->create()] as $autre) {
            $this->actingAs($autre)->getJson('/api/admin/utilisateurs')->assertForbidden();
            $this->actingAs($autre)->postJson('/api/admin/utilisateurs', $this->donneesCompte())->assertForbidden();
        }

        $this->getJson('/api/admin/utilisateurs')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/utilisateurs')->assertUnauthorized();
    }

    public function test_liste_filtrable_sans_mot_de_passe(): void
    {
        User::factory()->create(['nom' => 'Alpha']);
        User::factory()->secretaire()->create(['nom' => 'Bravo']);

        $this->actingAs($this->admin)->getJson('/api/admin/utilisateurs?role=secretaire')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Bravo')
            ->assertJsonMissingPath('data.0.password');

        $this->actingAs($this->admin)->getJson('/api/admin/utilisateurs?recherche=alph')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Alpha');
    }

    public function test_creation_d_une_secretaire(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/utilisateurs', $this->donneesCompte())
            ->assertCreated()
            ->assertJsonPath('data.role', 'secretaire');

        $this->assertTrue(User::where('email', 'personnel@exemple.test')->first()->aLeRole(Role::Secretaire));
    }

    public function test_creation_d_un_medecin_avec_sa_specialite(): void
    {
        $cardio = Specialite::where('libelle', 'Cardiologie')->first();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/utilisateurs', $this->donneesCompte(['role' => 'medecin', 'specialite_id' => $cardio->id]))
            ->assertCreated()
            ->assertJsonPath('data.specialite.libelle', 'Cardiologie');

        $user = User::where('email', 'personnel@exemple.test')->first();
        $this->assertSame($cardio->id, Medecin::find($user->id)->specialite_id);
    }

    public function test_un_medecin_sans_specialite_est_refuse(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/utilisateurs', $this->donneesCompte(['role' => 'medecin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('specialite_id');

        $this->assertSame(0, Medecin::count());
    }

    public function test_l_administrateur_ne_cree_pas_de_compte_patient(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/utilisateurs', $this->donneesCompte(['role' => 'patient']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_modification_des_coordonnees_du_role_et_de_la_specialite(): void
    {
        $secretaire = User::factory()->secretaire()->create();
        $medecin = User::factory()->medecin()->create();
        $orl = Specialite::where('libelle', 'ORL')->first();

        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$secretaire->id}", ['telephone' => '00 000 00 09', 'role' => 'administrateur'])
            ->assertOk()
            ->assertJsonPath('data.telephone', '00 000 00 09')
            ->assertJsonPath('data.role', 'administrateur');

        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$medecin->id}", ['specialite_id' => $orl->id])
            ->assertOk()
            ->assertJsonPath('data.specialite.libelle', 'ORL');
    }

    public function test_le_role_d_un_patient_ou_d_un_medecin_ne_change_pas(): void
    {
        $patient = User::factory()->create();
        $medecin = User::factory()->medecin()->create();

        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$patient->id}", ['role' => 'secretaire'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$medecin->id}", ['role' => 'secretaire'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_desactivation_sans_suppression_physique(): void
    {
        $patient = User::factory()->create(['email' => 'patient@exemple.test', 'password' => 'motdepasse1']);

        $this->actingAs($this->admin)->postJson("/api/admin/utilisateurs/{$patient->id}/desactivation")
            ->assertOk()
            ->assertJsonPath('data.actif', false);

        $this->assertDatabaseHas('users', ['id' => $patient->id, 'actif' => false]);

        // Aucune route de suppression.
        $this->actingAs($this->admin)->deleteJson("/api/admin/utilisateurs/{$patient->id}")->assertStatus(405);

        // Réactivation possible.
        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$patient->id}", ['actif' => true])
            ->assertOk()->assertJsonPath('data.actif', true);
    }

    public function test_l_administrateur_ne_desactive_pas_son_propre_compte(): void
    {
        $this->actingAs($this->admin)->postJson("/api/admin/utilisateurs/{$this->admin->id}/desactivation")
            ->assertForbidden()
            ->assertJsonPath('message', 'Vous ne pouvez pas désactiver votre propre compte.');
    }

    public function test_liste_publique_des_specialites(): void
    {
        $this->getJson('/api/specialites')->assertOk()->assertJsonCount(10, 'data');
    }
}
