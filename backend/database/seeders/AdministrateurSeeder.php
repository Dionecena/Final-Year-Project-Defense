<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('auth.administrateur_initial.email');
        $password = config('auth.administrateur_initial.password');
        $telephone = config('auth.administrateur_initial.telephone');

        if (blank($email) || blank($password) || blank($telephone)) {
            throw new RuntimeException('Renseignez ADMIN_EMAIL, ADMIN_PASSWORD et ADMIN_TELEPHONE dans le fichier .env avant de lancer ce seeder.');
        }

        $admin = User::firstOrNew(['email' => $email]);

        // Le mot de passe n'est défini qu'à la création : relancer le seeder ne l'écrase pas.
        if (! $admin->exists) {
            $admin->password = $password;
        }

        $admin->fill([
            'nom' => 'Administrateur',
            'prenom' => 'Compte',
            'telephone' => $telephone,
            'role' => Role::Administrateur->value,
            'actif' => true,
        ])->save();
    }
}
