<?php

namespace App\Http\Requests\RendezVous;

use Illuminate\Support\Facades\Gate;

/**
 * Changement de créneau (même médecin ou autre médecin de la même spécialité), par le patient
 * dans le délai de RG08 ou par la secrétaire (RendezVousPolicy::modifier).
 */
class UpdateRendezVousRequest extends StoreRendezVousRequest
{
    public function authorize(): mixed
    {
        return Gate::inspect('modifier', $this->route('rendezVous'));
    }
}
