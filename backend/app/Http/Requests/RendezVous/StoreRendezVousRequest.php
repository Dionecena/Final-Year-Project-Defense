<?php

namespace App\Http\Requests\RendezVous;

use App\Http\Requests\Concerns\ReglesPreconsultation;
use App\Models\Medecin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réservation d'un créneau par le patient, avec la préconsultation facultative (RG02) :
 * { medecin_id, date_heure, preconsultation?: { motif, symptomes } }. La disponibilité réelle du
 * créneau est revérifiée par RendezVousService dans la transaction (RG05).
 */
class StoreRendezVousRequest extends FormRequest
{
    use ReglesPreconsultation;

    public function authorize(): mixed
    {
        // Rôle déjà contrôlé par le middleware role:patient.
        return true;
    }

    public function rules(): array
    {
        $medecin = filter_var($this->input('medecin_id'), FILTER_VALIDATE_INT) !== false
            ? Medecin::with('specialite')->find($this->input('medecin_id'))
            : null;

        return [
            ...$this->reglesCreneau(),
            // Collecte minimale (section 10) : aucune autre clé que motif et symptômes.
            'preconsultation' => ['nullable', 'array:motif,symptomes,consentement'],
            // RG11 : longueur des symptômes selon la spécialité du médecin choisi.
            ...$this->reglesPreconsultation($medecin?->specialite, 'preconsultation.', obligatoire: false),
            // Données de santé (loi n° 2008-12) : consentement explicite au traitement.
            // (accepted échoue aussi si la clé manque : la règle ne s'applique qu'avec une préconsultation.)
            'preconsultation.consentement' => ['exclude_without:preconsultation', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'medecin_id.exists' => 'Ce médecin n\'existe pas.',
            'date_heure.date_format' => 'La date et l\'heure doivent être au format AAAA-MM-JJ HH:MM.',
            'preconsultation.array' => 'La préconsultation ne contient que le motif et les symptômes.',
            'preconsultation.consentement.accepted' => 'Vous devez consentir au traitement de vos données de santé pour transmettre la préconsultation.',
            ...$this->messagesPreconsultation('preconsultation.'),
        ];
    }

    protected function reglesCreneau(): array
    {
        return [
            'medecin_id' => ['required', 'integer', 'exists:medecins,user_id'],
            'date_heure' => ['required', 'date_format:Y-m-d H:i'],
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

    /** @return array{motif: string, symptomes: string}|null null : préconsultation passée (RG02) */
    public function preconsultation(): ?array
    {
        $preconsultation = $this->validated('preconsultation');

        return empty($preconsultation) ? null : $preconsultation;
    }
}
