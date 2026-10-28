<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Classe Utilisateur du diagramme de classes. Les sous-classes Patient, Secretaire,
 * Medecin et Administrateur partagent cette table et se distinguent par la colonne role.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'password',
        'role',
        'actif',
        'date_consentement',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'actif' => 'boolean',
            'date_consentement' => 'datetime',
        ];
    }

    // Pas de colonne remember_token : la case « se souvenir de moi » n'est pas prévue.
    public function getRememberTokenName()
    {
        return '';
    }

    public function aLeRole(Role|string $role): bool
    {
        $valeur = $role instanceof Role ? $role->value : $role;

        return $this->role?->value === $valeur;
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    /** Fiche médecin (table medecins), présente seulement pour le rôle medecin. */
    public function medecin(): HasOne
    {
        return $this->hasOne(Medecin::class, 'user_id');
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class, 'patient_id');
    }

    public function scopeRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->value);
    }

    public function scopePatients(Builder $query): Builder
    {
        return $query->where('role', Role::Patient->value);
    }

    public function scopeSecretaires(Builder $query): Builder
    {
        return $query->where('role', Role::Secretaire->value);
    }

    public function scopeMedecins(Builder $query): Builder
    {
        return $query->where('role', Role::Medecin->value);
    }

    public function scopeAdministrateurs(Builder $query): Builder
    {
        return $query->where('role', Role::Administrateur->value);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
