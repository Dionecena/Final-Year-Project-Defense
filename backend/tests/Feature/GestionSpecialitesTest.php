<?php

namespace Tests\Feature;

use App\Models\Specialite;
use App\Models\User;
use Database\Seeders\SpecialiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 3 : gestion des spécialités par l'administrateur et liste publique.
 */
class GestionSpecialitesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialiteSeeder::class);
        $this->admin = User::factory()->administrateur()->create();
    }

    public function test_liste_publique_sans_authentification_avec_les_jours_de_presence(): void
    {
        $this->getJson('/api/specialites')
            ->assertOk()
            ->assertJsonCount(11, 'data')
            ->assertJsonFragment(['libelle' => 'Ophtalmologie', 'description' => 'Jours de présence : Mardi'])
            ->assertJsonMissingPath('data.0.nombre_medecins');
    }

    public function test_seul_l_administrateur_gere_les_specialites(): void
    {
        $orl = Specialite::where('libelle', 'ORL')->first();

        foreach ([User::factory()->create(), User::factory()->secretaire()->create(), User::factory()->medecin()->create()] as $autre) {
            $this->actingAs($autre)->getJson('/api/admin/specialites')->assertForbidden();
            $this->actingAs($autre)->postJson('/api/admin/specialites', ['libelle' => 'Rhumatologie'])->assertForbidden();
            $this->actingAs($autre)->patchJson("/api/admin/specialites/{$orl->id}", ['description' => 'Modifiée'])->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/admin/specialites', ['libelle' => 'Rhumatologie'])->assertUnauthorized();

        $this->assertDatabaseMissing('specialites', ['libelle' => 'Rhumatologie']);
        $this->assertSame('Jours de présence : Lundi, mercredi, vendredi', $orl->fresh()->description);
    }

    public function test_l_administrateur_liste_les_specialites_avec_le_nombre_de_medecins(): void
    {
        User::factory()->medecin(Specialite::where('libelle', 'Cardiologie')->first())->create();

        $this->actingAs($this->admin)->getJson('/api/admin/specialites')
            ->assertOk()
            ->assertJsonCount(11, 'data')
            ->assertJsonFragment(['libelle' => 'Cardiologie', 'nombre_medecins' => 1]);
    }

    public function test_creation_d_une_specialite(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/specialites', ['libelle' => '  Spécialité   Démo ', 'description' => 'Jours de présence : Mardi'])
            ->assertCreated()
            ->assertJsonPath('data.libelle', 'Spécialité Démo');

        $this->assertDatabaseHas('specialites', ['libelle' => 'Spécialité Démo', 'description' => 'Jours de présence : Mardi']);
    }

    public function test_la_description_est_facultative(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/specialites', ['libelle' => 'Spécialité Démo', 'description' => ''])
            ->assertCreated()
            ->assertJsonPath('data.description', null);
    }

    public function test_le_libelle_est_obligatoire(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/specialites', ['description' => 'Mardi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['libelle' => 'Le champ libellé est obligatoire.']);
    }

    public function test_unicite_du_libelle_sans_tenir_compte_de_la_casse(): void
    {
        foreach (['ORL', 'orl', ' Cardiologie '] as $libelle) {
            $this->actingAs($this->admin)->postJson('/api/admin/specialites', ['libelle' => $libelle])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['libelle' => 'Une spécialité porte déjà ce libellé.']);
        }

        $this->assertSame(11, Specialite::count());
    }

    public function test_modification_du_libelle_et_de_la_description(): void
    {
        $neuro = Specialite::where('libelle', 'Neurologie')->first();

        // Conserver son propre libellé n'est pas un doublon.
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/specialites/{$neuro->id}", ['libelle' => 'Neurologie', 'description' => 'Jours de présence : Mercredi, jeudi'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Jours de présence : Mercredi, jeudi');

        $this->actingAs($this->admin)->patchJson("/api/admin/specialites/{$neuro->id}", ['libelle' => 'Neurochirurgie'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('libelle');

        $this->assertSame('Neurologie', $neuro->fresh()->libelle);
    }

    public function test_aucune_suppression_de_specialite(): void
    {
        $orl = Specialite::where('libelle', 'ORL')->first();

        $this->actingAs($this->admin)->deleteJson("/api/admin/specialites/{$orl->id}")->assertMethodNotAllowed();
        $this->actingAs($this->admin)->deleteJson('/api/specialites')->assertMethodNotAllowed();

        $this->assertModelExists($orl);
    }
}
