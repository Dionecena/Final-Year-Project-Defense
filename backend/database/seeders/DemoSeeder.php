<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutRdv;
use App\Models\Disponibilite;
use App\Models\Indisponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use App\Services\OrientationService;
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
 * les jours de présence, plages horaires fictives), trois patients, quelques rendez-vous et une
 * indisponibilité avec un rendez-vous « à reprogrammer ».
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

        $secretaire = $this->compte('secretaire.demo@exemple.test', 'Démo', 'Secrétaire', Role::Secretaire, $password);

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
        $generale = $this->rendezVous($patients[0], $medecins['Médecine générale'], 1, 0);
        $this->rendezVous($patients[1], $medecins['Cardiologie'], 1, 1);
        $ophtalmologie = $this->rendezVous($patients[2], $medecins['Ophtalmologie'], 2, 2);
        $this->rendezVous($patients[0], $medecins['Pédiatrie'], 3, 1);

        // Historique fictif : un rendez-vous annulé et un rendez-vous passé.
        $this->rendezVous($patients[0], $medecins['Dermatologie'], 1, 3, StatutRdv::Annule);
        $this->rendezVous($patients[0], $medecins['Médecine générale'], -2, 2, StatutRdv::Passe);

        // Indisponibilité fictive (module 7) : matinée du médecin d'ORL, dont le rendez-vous est
        // « à reprogrammer ». Créés directement, sans passer par le service, pour n'envoyer aucun courriel.
        $aReprogrammer = $this->rendezVous($patients[2], $medecins['ORL'], 2, 1, StatutRdv::AReprogrammer);
        $jour = $aReprogrammer->date_heure->toImmutable()->startOfDay();
        Indisponibilite::firstOrCreate(
            ['medecin_id' => $medecins['ORL']->user_id, 'date_debut' => $jour->setTime(9, 0), 'date_fin' => $jour->setTime(12, 0)],
            ['enregistree_par' => $secretaire->id, 'motif' => 'Formation (exemple fictif)'],
        );

        // Préconsultations fictives (module 6) : l'une sans suggestion, l'autre où le patient a
        // conservé sa spécialité malgré la suggestion (specialite_suggeree_id renseignée).
        $this->preconsultation($generale, 'Fatigue depuis une semaine', 'Fatigue persistante et légère fièvre le soir (texte fictif).');
        $this->preconsultation($ophtalmologie, 'Contrôle annuel', 'Plaques rouges sur la peau du visage depuis quelques jours (texte fictif).');
    }

    private function preconsultation(RendezVous $rendezVous, string $motif, string $symptomes): void
    {
        $specialite = $rendezVous->medecin->specialite;

        $rendezVous->preconsultation()->firstOrCreate([], [
            'motif' => $motif,
            'symptomes' => $symptomes,
            'specialite_suggeree_id' => app(OrientationService::class)->specialiteSuggeree($specialite, $motif, $symptomes)?->id,
            'date_saisie' => CarbonImmutable::now(),
            'date_consentement' => CarbonImmutable::now(),
        ]);
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
            // Numéro manifestement fictif.
            'telephone' => '00 000 00 00',
            'role' => $role->value,
            'actif' => true,
            // Consentement fictif à l'inscription, conservé si le seeder est relancé.
            'date_consentement' => $role === Role::Patient ? ($user->date_consentement ?? CarbonImmutable::now()) : null,
        ])->save();

        return $user;
    }

    /**
     * @param  int  $occurrence  n-ième prochain jour de présence du médecin (1 = le prochain ;
     *                            -1 = le dernier jour de présence passé, avant aujourd'hui)
     * @param  int  $indexCreneau  rang du créneau dans la plage (0 = le premier)
     */
    private function rendezVous(User $patient, Medecin $medecin, int $occurrence, int $indexCreneau, StatutRdv $statut = StatutRdv::Confirme): RendezVous
    {
        $jours = $medecin->disponibilites()->pluck('jour_semaine')->all();
        $pas = $occurrence > 0 ? 1 : -1;
        $date = $occurrence > 0 ? CarbonImmutable::tomorrow() : CarbonImmutable::yesterday();
        $trouves = 0;

        while (true) {
            if (in_array($date->dayOfWeekIso, $jours, true) && ++$trouves === abs($occurrence)) {
                break;
            }
            $date = $date->addDays($pas);
        }

        [$h, $m] = explode(':', self::HEURE_DEBUT);
        $dateHeure = $date->setTime((int) $h, (int) $m)->addMinutes($indexCreneau * self::DUREE_CRENEAU);

        return RendezVous::firstOrCreate(
            ['medecin_id' => $medecin->user_id, 'date_heure' => $dateHeure, 'statut' => $statut->value],
            ['patient_id' => $patient->id],
        );
    }
}
