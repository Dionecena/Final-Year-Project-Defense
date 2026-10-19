<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Medecin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gestion des comptes par l'administrateur. Aucune suppression physique : « supprimer »
 * un compte revient à le désactiver.
 */
class UtilisateurService
{
    /** @param array<string, mixed> $donnees */
    public function creer(array $donnees): User
    {
        return DB::transaction(function () use ($donnees) {
            $user = User::create([
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'],
                'password' => $donnees['password'],
                'role' => $donnees['role'],
                'actif' => true,
            ]);

            if ($user->aLeRole(Role::Medecin)) {
                Medecin::create(['user_id' => $user->id, 'specialite_id' => $donnees['specialite_id']]);
            }

            return $user->load('medecin.specialite');
        });
    }

    /** @param array<string, mixed> $donnees */
    public function modifier(User $user, array $donnees): User
    {
        return DB::transaction(function () use ($user, $donnees) {
            $user->fill(array_intersect_key($donnees, array_flip(['nom', 'prenom', 'email', 'telephone', 'role', 'actif'])));

            if (filled($donnees['password'] ?? null)) {
                $user->password = $donnees['password'];
            }

            $user->save();

            if ($user->aLeRole(Role::Medecin) && array_key_exists('specialite_id', $donnees)) {
                Medecin::updateOrCreate(['user_id' => $user->id], ['specialite_id' => $donnees['specialite_id']]);
            }

            return $user->load('medecin.specialite');
        });
    }

    public function desactiver(User $user): User
    {
        $user->update(['actif' => false]);

        // Ferme les sessions ouvertes du compte désactivé.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return $user->load('medecin.specialite');
    }
}
