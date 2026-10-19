<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Module 2 : inscription du patient, connexion et déconnexion (T01, T02).
 */
class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    private function donneesInscription(array $surcharge = []): array
    {
        return $surcharge + [
            'nom' => 'Testeur',
            'prenom' => 'Patient',
            'email' => 'patient@exemple.test',
            'telephone' => '00 000 00 01',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
        ];
    }

    public function test_inscription_cree_un_compte_patient_et_ouvre_la_session(): void
    {
        $this->withHeaders($this->enTetesSpa())
            ->postJson('/api/inscription', $this->donneesInscription(['email' => '  Patient@Exemple.TEST ']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'patient')
            ->assertJsonPath('data.email', 'patient@exemple.test')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'patient@exemple.test')->firstOrFail();
        $this->assertNotSame('motdepasse1', $user->password);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_inscription_ignore_un_role_fourni_par_le_client(): void
    {
        $this->withHeaders($this->enTetesSpa())
            ->postJson('/api/inscription', $this->donneesInscription(['role' => 'administrateur']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'patient');
    }

    /** T01 */
    public function test_t01_inscription_avec_une_adresse_deja_utilisee_est_refusee(): void
    {
        User::factory()->create(['email' => 'patient@exemple.test']);

        $this->withHeaders($this->enTetesSpa())
            ->postJson('/api/inscription', $this->donneesInscription(['email' => 'PATIENT@exemple.test']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Cette adresse électronique est déjà utilisée.');

        $this->assertSame(1, User::count());
    }

    public function test_inscription_valide_les_champs_obligatoires(): void
    {
        $this->withHeaders($this->enTetesSpa())
            ->postJson('/api/inscription', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'prenom', 'email', 'telephone', 'password']);
    }

    public function test_connexion_reussie_puis_deconnexion(): void
    {
        $user = User::factory()->create(['email' => 'patient@exemple.test', 'password' => 'motdepasse1']);

        $this->postJson('/login', ['email' => 'patient@exemple.test', 'password' => 'motdepasse1'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
        $this->assertAuthenticatedAs($user, 'web');

        $this->postJson('/logout')->assertOk();
        $this->assertGuest('web');
    }

    /** T02 (1/2) */
    public function test_t02_mauvais_mot_de_passe_renvoie_un_message_generique(): void
    {
        User::factory()->create(['email' => 'patient@exemple.test', 'password' => 'motdepasse1']);

        $this->postJson('/login', ['email' => 'patient@exemple.test', 'password' => 'mauvais'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Identifiants incorrects.');

        // Même message pour une adresse inconnue : l'existence du compte n'est pas révélée.
        $this->postJson('/login', ['email' => 'inconnu@exemple.test', 'password' => 'mauvais'])
            ->assertJsonPath('errors.email.0', 'Identifiants incorrects.');

        $this->assertGuest('web');
    }

    /** T02 (2/2) */
    public function test_t02_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->inactif()->create(['email' => 'patient@exemple.test', 'password' => 'motdepasse1']);

        $this->postJson('/login', ['email' => 'patient@exemple.test', 'password' => 'motdepasse1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Identifiants incorrects.');

        $this->assertGuest('web');
        $this->getJson('/api/moi')->assertUnauthorized();
    }

    public function test_connexion_limitee_a_cinq_tentatives_par_minute(): void
    {
        RateLimiter::clear('connexion|patient@exemple.test|127.0.0.1');
        User::factory()->create(['email' => 'patient@exemple.test', 'password' => 'motdepasse1']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'patient@exemple.test', 'password' => 'mauvais'])->assertUnprocessable();
        }

        // Même avec le bon mot de passe, la sixième tentative est bloquée.
        $this->postJson('/login', ['email' => 'patient@exemple.test', 'password' => 'motdepasse1'])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', fn (string $m) => str_starts_with($m, 'Trop de tentatives de connexion.'));

        $this->assertGuest('web');
    }
}
