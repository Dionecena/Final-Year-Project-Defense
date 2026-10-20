<?php

namespace App\Http\Requests\Secretariat;

use App\Models\Disponibilite;
use App\Models\Medecin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ajout d'une disponibilité hebdomadaire par la secrétaire. Une disponibilité n'est ensuite
 * ni modifiée ni supprimée (RG14).
 */
class StoreDisponibiliteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rôle déjà contrôlé par le middleware role:secretaire.
        return true;
    }

    public function rules(): array
    {
        return [
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'duree_creneau' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'jour_semaine.between' => 'Le jour doit être compris entre 1 (lundi) et 7 (dimanche).',
            'heure_debut.date_format' => 'L\'heure de début doit être au format HH:MM.',
            'heure_fin.date_format' => 'L\'heure de fin doit être au format HH:MM.',
            'heure_fin.after' => 'L\'heure de fin doit être postérieure à l\'heure de début.',
            'duree_creneau.min' => 'La durée du créneau doit être supérieure à 0 minute.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $medecin = $this->medecin();

            if (! $medecin->user?->actif) {
                $validator->errors()->add('medecin_id', 'Ce médecin est désactivé : aucune disponibilité ne peut lui être ajoutée.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $debut = $this->input('heure_debut');
            $fin = $this->input('heure_fin');

            // Un créneau qui dépasse heure_fin est ignoré (section 8.1) : la plage doit en contenir au moins un.
            $minutes = CarbonImmutable::createFromFormat('!H:i', $debut)->diffInMinutes(CarbonImmutable::createFromFormat('!H:i', $fin));
            if ((int) $this->input('duree_creneau') > $minutes) {
                $validator->errors()->add('duree_creneau', 'La durée du créneau dépasse la durée de la plage horaire.');
            }

            // Deux plages d'un même médecin et d'un même jour ne se chevauchent pas (décision de l'auteur).
            // Des plages qui se touchent (09:00–12:00 puis 12:00–14:00) sont acceptées.
            $existante = Disponibilite::query()
                ->where('medecin_id', $medecin->user_id)
                ->where('jour_semaine', (int) $this->input('jour_semaine'))
                ->where('heure_debut', '<', $fin)
                ->where('heure_fin', '>', $debut)
                ->orderBy('heure_debut')
                ->first();

            if ($existante) {
                $validator->errors()->add('heure_debut', sprintf(
                    'Cette plage chevauche une disponibilité existante du même jour (%s–%s).',
                    substr($existante->heure_debut, 0, 5),
                    substr($existante->heure_fin, 0, 5),
                ));
            }
        }];
    }

    public function medecin(): Medecin
    {
        return $this->route('medecin');
    }
}
