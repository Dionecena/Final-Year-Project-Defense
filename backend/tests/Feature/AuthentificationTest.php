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
            'consentement' => true,
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

    public function test_inscription_exige_le_consentement_et_l_enregistre(): void
    {
        $sansConsentement = array_diff_key($this->donneesInscription(), ['consentement' => true]);
        foreach ([$sansConsentement, ['consentement' => false] + $sansConsentement] as $donnees) {
            $this->withHeaders($this->enTetesSpa())
                ->postJson('/api/inscription', $donnees)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['consentement' => 'Vous devez accepter la politique de confidentialité et les conditions d\'utilisation.']);
        }
        $this->assertSame(0, User::count());

        $this->withHeaders($this->enTetesSpa())->postJson('/api/inscription', $this->donneesInscription())->assertCreated();
        $this->assertNotNull(User::sole()->date_consentement);
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

    public function test_au_plus_trois_comptes_patients_par_appareil_et_par_heure(): void
    {
        $patients = User::factory()->count(4)->sequence(fn ($s) => ['email' => "patient{$s->index}@exemple.test", 'password' => 'motdepasse1'])->create();

        foreach ($patients->take(3) as $patient) {
            $this->postJson('/login', ['email' => $patient->email, 'password' => 'motdepasse1'])->assertOk();
            $this->postJson('/logout')->assertOk();
        }

        // Quatrième compte différent depuis la même adresse IP : refusé, même avec le bon mot de passe.
        $this->postJson('/login', ['email' => 'patient3@exemple.test', 'password' => 'motdepasse1'])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', 'Trop de comptes différents ont été utilisés depuis cet appareil. Réessayez dans une heure.');
        $this->assertGuest('web');

        // Un compte déjà utilisé sur l'appareil peut toujours se reconnecter.
        $this->postJson('/login', ['email' => 'patient0@exemple.test', 'password' => 'motdepasse1'])->assertOk();

        // Une heure plus tard, la limite est levée.
        $this->travel(61)->minutes();
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => 'patient3@exemple.test', 'password' => 'motdepasse1'])->assertOk();
    }

    public function test_le_personnel_n_est_pas_limite_par_appareil(): void
    {
        $patients = User::factory()->count(3)->sequence(fn ($s) => ['email' => "patient{$s->index}@exemple.test", 'password' => 'motdepasse1'])->create();
        foreach ($patients as $patient) {
            $this->postJson('/login', ['email' => $patient->email, 'password' => 'motdepasse1'])->assertOk();
            $this->postJson('/logout');
        }

        User::factory()->secretaire()->create(['email' => 'secretaire@exemple.test', 'password' => 'motdepasse1']);
        $this->postJson('/login', ['email' => 'secretaire@exemple.test', 'password' => 'motdepasse1'])->assertOk();
    }

    public function test_l_inscription_compte_parmi_les_comptes_de_l_appareil(): void
    {
        foreach (range(1, 3) as $i) {
            $this->withHeaders($this->enTetesSpa())
                ->postJson('/api/inscription', $this->donneesInscription(['email' => "nouveau{$i}@exemple.test"]))
                ->assertCreated();
            $this->postJson('/logout');
        }

        $this->withHeaders($this->enTetesSpa())
            ->postJson('/api/inscription', $this->donneesInscription(['email' => 'nouveau4@exemple.test']))
            ->assertStatus(429);
        $this->assertDatabaseMissing('users', ['email' => 'nouveau4@exemple.test']);
    }

    public function test_un_script_qui_change_d_adresse_est_freine_par_adresse_ip(): void
    {
        // 20 tentatives par minute depuis une même adresse IP, quelle que soit l'adresse électronique.
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/login', ['email' => "inconnu{$i}@exemple.test", 'password' => 'mauvais'])->assertUnprocessable();
        }

        $this->postJson('/login', ['email' => 'autre@exemple.test', 'password' => 'mauvais'])->assertStatus(429);
    }

    public function test_inscription_refuse_une_adresse_dont_le_domaine_n_existe_pas(): void
    {
        config(['clinique.verifier_domaine_email' => true]);

        $this->withHeaders($this->enTetesSpa())
            ->postJson('/api/inscription', $this->donneesInscription(['email' => 'patient@domaine-inexistant.invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_connexion_de_demonstration_fermee_par_defaut(): void
    {
        User::factory()->secretaire()->create(['email' => 'secretaire.demo@exemple.test']);

        $this->getJson('/demo/secretariat')->assertNotFound();
        $this->postJson('/demo/secretariat')->assertNotFound();
        $this->assertGuest('web');
    }

    public function test_connexion_de_demonstration_ouvre_le_seul_compte_secretaire_designe(): void
    {
        $secretaire = User::factory()->secretaire()->create(['email' => 'secretaire.demo@exemple.test']);
        User::factory()->create(['email' => 'patient@exemple.test']);

        config(['clinique.demo_secretariat' => 'patient@exemple.test']);
        $this->postJson('/demo/secretariat')->assertNotFound();
        $this->assertGuest('web');

        config(['clinique.demo_secretariat' => 'secretaire.demo@exemple.test']);
        $this->getJson('/demo/secretariat')->assertNoContent();
        $this->postJson('/demo/secretariat')->assertOk()->assertJsonPath('data.role', 'secretaire');
        $this->assertAuthenticatedAs($secretaire, 'web');
    }
}
