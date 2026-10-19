<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\Concerns\ReglesUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUtilisateurRequest extends FormRequest
{
    use ReglesUtilisateur;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->cible());
    }

    public function rules(): array
    {
        $cible = $this->cible();

        return [
            'nom' => $this->regleNom(false),
            'prenom' => $this->regleNom(false),
            'email' => $this->regleEmail($cible->id, false),
            'telephone' => $this->regleTelephone(false),
            'password' => $this->reglePassword(false),
            'role' => ['sometimes', Rule::in(StoreUtilisateurRequest::rolesPersonnel())],
            'specialite_id' => ['sometimes', 'nullable', 'integer', 'exists:specialites,id'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Le rôle doit être secrétaire, médecin ou administrateur.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $cible = $this->cible();
            $roleActuel = $cible->role->value;
            $nouveauRole = $this->input('role', $roleActuel);

            if ($this->has('role') && $nouveauRole !== $roleActuel) {
                if ($cible->aLeRole(Role::Patient)) {
                    $validator->errors()->add('role', 'Le rôle d\'un compte patient ne peut pas être modifié.');
                } elseif ($cible->aLeRole(Role::Medecin)) {
                    // Les disponibilités et les rendez-vous restent rattachés à la fiche médecin.
                    $validator->errors()->add('role', 'Le rôle d\'un médecin ne peut pas être modifié : désactivez le compte et créez-en un nouveau.');
                } elseif ($cible->is($this->user())) {
                    $validator->errors()->add('role', 'Vous ne pouvez pas modifier votre propre rôle.');
                }
            }

            if ($this->has('specialite_id')) {
                if ($nouveauRole !== Role::Medecin->value) {
                    $validator->errors()->add('specialite_id', 'Seul un médecin a une spécialité.');
                } elseif (blank($this->input('specialite_id'))) {
                    $validator->errors()->add('specialite_id', 'La spécialité est obligatoire pour un médecin.');
                }
            } elseif ($nouveauRole === Role::Medecin->value && $roleActuel !== Role::Medecin->value) {
                $validator->errors()->add('specialite_id', 'La spécialité est obligatoire pour un médecin.');
            }

            if ($this->input('actif') === false || $this->input('actif') === 0 || $this->input('actif') === '0') {
                $validator->errors()->add('actif', 'Utilisez l\'action « Désactiver » pour désactiver un compte.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliserChamps();
    }

    private function cible(): User
    {
        return $this->route('utilisateur');
    }
}
