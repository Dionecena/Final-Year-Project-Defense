<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutRdv;
use App\Models\Disponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DONNÉES DE DÉMONSTRATION ENTIÈREMENT FICTIVES — non exécuté par défaut.
 *
 * Lancement : php artisan db:seed --class=DemoSeeder
 *
 * Crée une secrétaire, un médecin fictif par spécialité (disponibilités cohérentes avec
 * les jours de présence, plages horaires fictives), trois patients et quelques rendez-vous.
 * Tous les comptes utilisent le mot de passe DEMO_PASSWORD défini dans .env.
 */
class DemoSeeder extends Seeder
{
    /** Jours de présence (1 = lundi … 7 = dimanche), repris de SpecialiteSeeder. */
    private const JOURS = [
        'Médecine générale' => [1, 2, 3, 4, 5, 6, 7],
        'Pédiatrie' => [1, 2, 3, 4, 5, 6, 7],
        'ORL' => [1, 3, 5],
        'Cardiologie' => [1, 3],
        'Ophtalmologie' => [2],
        'Psychiatrie' => [1, 5, 6],
        'Dermatologie' => [3, 6],
        'Neurologie' => [3],
        'Neurochirurgie' => [4],
        'Chirurgie générale' => [4],
    ];

    // Plage horaire fictive commune à tous les médecins de démonstration.
    private const HEURE_DEBUT = '09:00';

    private const HEURE_FIN = '12:00';

    private const DUREE_CRENEAU = 30;

    public function run(): void
    {
        $password = config('auth.demo_password');

        if (blank($password)) {
            throw new RuntimeException('Renseignez DEMO_PASSWORD dans le fichier .env avant de lancer DemoSeeder.');
        }

        $this->call(SpecialiteSeeder::class);

        $this->compte('secretaire.demo@exemple.test', 'Démo', 'Secrétaire', Role::Secretaire, $password);

        $medecins = [];
        foreach (self::JOURS as $libelle => $jours) {
            $specialite = Specialite::where('libelle', $libelle)->firstOrFail();
            $slug = Str::slug($libelle);

            $user = $this->compte("medecin.{$slug}@exemple.test", 'Démo '.$libelle, 'Médecin', Role::Medecin, $password);
            $medecin = Medecin::updateOrCreate(['user_id' => $user->id], ['specialite_id' => $specialite->id]);

            foreach ($jours as $jour) {
                Disponibilite::firstOrCreate([
                    'medecin_id' => $medecin->user_id,
                    'jour_semaine' => $jour,
                    'heure_debut' => self::HEURE_DEBUT,
                    'heure_fin' => self::HEURE_FIN,
                    'duree_creneau' => self::DUREE_CRENEAU,
                ]);
            }

            $medecins[$libelle] = $medecin;
        }

        $patients = [
            $this->compte('patient1.demo@exemple.test', 'Démo Un', 'Patient', Role::Patient, $password),
            $this->compte('patient2.demo@exemple.test', 'Démo Deux', 'Patient', Role::Patient, $password),
            $this->compte('patient3.demo@exemple.test', 'Démo Trois', 'Patient', Role::Patient, $password),
        ];

        // Quelques rendez-vous futurs, placés sur le premier créneau des prochains jours de présence.
        $this->rendezVous($patients[0], $medecins['Médecine générale'], 1, 0);
        $this->rendezVous($patients[1], $medecins['Cardiologie'], 1, 1);
        $this->rendezVous($patients[2], $medecins['Ophtalmologie'], 2, 2);
    }

    private function compte(string $email, string $nom, string $prenom, Role $role, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->password = $password;
        }
        $user->fill([
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => null,
            'role' => $role->value,
            'actif' => true,
        ])->save();

        return $user;
    }

    /**
     * @param  int  $occurrence  n-ième prochain jour de présence du médecin (1 = le prochain)
     * @param  int  $indexCreneau  rang du créneau dans la plage (0 = le premier)
     */
    private function rendezVous(User $patient, Medecin $medecin, int $occurrence, int $indexCreneau): void
    {
        $jours = $medecin->disponibilites()->pluck('jour_semaine')->all();
        $date = CarbonImmutable::tomorrow();
        $trouves = 0;

        while (true) {
            if (in_array($date->dayOfWeekIso, $jours, true) && ++$trouves === $occurrence) {
                break;
            }
            $date = $date->addDay();
        }

        [$h, $m] = explode(':', self::HEURE_DEBUT);
        $dateHeure = $date->setTime((int) $h, (int) $m)->addMinutes($indexCreneau * self::DUREE_CRENEAU);

        RendezVous::firstOrCreate(
            ['medecin_id' => $medecin->user_id, 'date_heure' => $dateHeure, 'statut' => StatutRdv::Confirme->value],
            ['patient_id' => $patient->id],
        );
    }
}
