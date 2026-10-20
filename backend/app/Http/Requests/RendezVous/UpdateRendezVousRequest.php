<?php

namespace App\Http\Requests\RendezVous;

use App\Enums\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Changement de créneau, par le patient dans le délai de RG08 ou par la secrétaire
 * (RendezVousPolicy::modifier). Décision de l'auteur : seule la secrétaire peut déplacer un
 * rendez-vous vers un autre médecin (de la même spécialité) ; le patient garde son médecin.
 * La préconsultation n'est pas modifiée.
 */
class UpdateRendezVousRequest extends StoreRendezVousRequest
{
    public function authorize(): mixed
    {
        return Gate::inspect('modifier', $this->route('rendezVous'));
    }

    public function rules(): array
    {
        return $this->reglesCreneau();
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $rendezVous = $this->route('rendezVous');

            if ($this->user()->aLeRole(Role::Patient)
                && (int) $this->input('medecin_id') !== (int) $rendezVous->medecin_id) {
                $validator->errors()->add('medecin_id', 'Vous ne pouvez changer que le créneau, avec le même médecin. Pour changer de médecin, contactez la clinique.');
            }
        }];
    }
}
