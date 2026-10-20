<?php

namespace App\Http\Requests\Secretariat;

use App\Models\Medecin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'une indisponibilité par la secrétaire (section 8.4) :
 * { date_debut, date_fin, motif? }, dates au format AAAA-MM-JJ HH:MM, heure de Dakar.
 */
class StoreIndisponibiliteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rôle déjà contrôlé par le middleware role:secretaire.
        return true;
    }

    public function rules(): array
    {
        return [
            'date_debut' => ['required', 'date_format:Y-m-d H:i'],
            // RG09 / section 8.4 : date_fin >= date_debut.
            'date_fin' => ['required', 'date_format:Y-m-d H:i', 'after_or_equal:date_debut', 'after:now'],
            'motif' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_debut.date_format' => 'La date de début doit être au format AAAA-MM-JJ HH:MM.',
            'date_fin.date_format' => 'La date de fin doit être au format AAAA-MM-JJ HH:MM.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'date_fin.after' => 'Cette période est déjà passée.',
        ];
    }

    public function medecin(): Medecin
    {
        return $this->route('medecin');
    }

    public function dateDebut(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->validated('date_debut'));
    }

    public function dateFin(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->validated('date_fin'));
    }

    public function motif(): ?string
    {
        $motif = trim((string) $this->validated('motif'));

        return $motif === '' ? null : $motif;
    }
}
