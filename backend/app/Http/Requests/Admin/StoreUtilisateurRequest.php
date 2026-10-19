<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\Concerns\ReglesUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un compte du personnel par l'administrateur (le patient s'inscrit lui-même).
 */
class StoreUtilisateurRequest extends FormRequest
{
    use ReglesUtilisateur;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'nom' => $this->regleNom(),
            'prenom' => $this->regleNom(),
            'email' => $this->regleEmail(),
            'telephone' => $this->regleTelephone(),
            'password' => $this->reglePassword(),
            'role' => ['required', Rule::in(self::rolesPersonnel())],
            'specialite_id' => ['required_if:role,medecin', 'prohibited_unless:role,medecin', 'nullable', 'integer', 'exists:specialites,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Le rôle doit être secrétaire, médecin ou administrateur.',
            'specialite_id.required_if' => 'La spécialité est obligatoire pour un médecin.',
            'specialite_id.prohibited_unless' => 'Seul un médecin a une spécialité.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliserChamps();
    }

    /** @return list<string> */
    public static function rolesPersonnel(): array
    {
        return [Role::Secretaire->value, Role::Medecin->value, Role::Administrateur->value];
    }
}
