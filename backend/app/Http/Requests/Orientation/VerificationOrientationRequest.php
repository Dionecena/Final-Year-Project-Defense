<?php

namespace App\Http\Requests\Orientation;

use App\Http\Requests\Concerns\ReglesPreconsultation;
use App\Models\Specialite;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vérification d'orientation avant la réservation (section 8.3) : spécialité choisie et
 * préconsultation saisie (motif et symptômes, mêmes règles que la réservation).
 */
class VerificationOrientationRequest extends FormRequest
{
    use ReglesPreconsultation;

    public function authorize(): bool
    {
        // Rôle déjà contrôlé par le middleware role:patient.
        return true;
    }

    public function rules(): array
    {
        $specialite = filter_var($this->input('specialite_id'), FILTER_VALIDATE_INT) !== false
            ? Specialite::find($this->input('specialite_id'))
            : null;

        return [
            'specialite_id' => ['required', 'integer', 'exists:specialites,id'],
            ...$this->reglesPreconsultation($specialite),
        ];
    }

    public function messages(): array
    {
        return [
            'specialite_id.exists' => 'Cette spécialité n\'existe pas.',
            ...$this->messagesPreconsultation(),
        ];
    }

    public function specialite(): Specialite
    {
        return Specialite::findOrFail($this->validated('specialite_id'));
    }
}
