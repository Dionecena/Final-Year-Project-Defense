<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Medecin;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake()->numerify('77 ### ## ##'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Patient->value,
            'actif' => true,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role->value]);
    }

    public function secretaire(): static
    {
        return $this->role(Role::Secretaire);
    }

    public function administrateur(): static
    {
        return $this->role(Role::Administrateur);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }

    /** Compte médecin avec sa fiche dans la table medecins. */
    public function medecin(?Specialite $specialite = null): static
    {
        return $this->role(Role::Medecin)->afterCreating(function (User $user) use ($specialite) {
            Medecin::create([
                'user_id' => $user->id,
                'specialite_id' => ($specialite ?? Specialite::firstOrCreate(['libelle' => 'Médecine générale']))->id,
            ]);
        });
    }
}
