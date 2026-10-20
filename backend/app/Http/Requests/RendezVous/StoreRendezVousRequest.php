<?php

namespace App\Http\Requests\RendezVous;

use App\Models\Medecin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réservation d'un créneau par le patient. La disponibilité réelle du créneau est revérifiée
 * par RendezVousService dans la transaction (RG05).
 */
class StoreRendezVousRequest extends FormRequest
{
    public function authorize(): mixed
    {
        // Rôle déjà contrôlé par le middleware role:patient.
        return true;
    }

    public function rules(): array
    {
        // Module 6 : préconsultation facultative (motif, symptômes) à ajouter ici.
        return [
            'medecin_id' => ['required', 'integer', 'exists:medecins,user_id'],
            'date_heure' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'medecin_id.exists' => 'Ce médecin n\'existe pas.',
            'date_heure.date_format' => 'La date et l\'heure doivent être au format AAAA-MM-JJ HH:MM.',
        ];
    }

    public function medecin(): Medecin
    {
        return Medecin::findOrFail($this->validated('medecin_id'));
    }

    /** Début du créneau, dans le fuseau de l'application (Africa/Dakar). */
    public function dateHeure(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->validated('date_heure'));
    }
}
