<?php

namespace App\Http\Requests\Admin;

use App\Models\Specialite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Création d'une spécialité. La description porte les jours de présence.
 */
class StoreSpecialiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Specialite::class);
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Libellé unique, sans tenir compte de la casse (« ORL » et « orl » sont la même spécialité). */
    public function after(): array
    {
        return [function (Validator $validator) {
            $libelle = $this->input('libelle');
            if (! is_string($libelle) || $libelle === '' || $validator->errors()->has('libelle')) {
                return;
            }

            $doublon = Specialite::query()
                ->whereRaw('LOWER(libelle) = ?', [mb_strtolower($libelle)])
                ->when($this->ignorerId(), fn ($q, $id) => $q->whereKeyNot($id))
                ->exists();

            if ($doublon) {
                $validator->errors()->add('libelle', 'Une spécialité porte déjà ce libellé.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $donnees = [];
        if (is_string($this->input('libelle'))) {
            $donnees['libelle'] = preg_replace('/\s+/u', ' ', trim($this->input('libelle')));
        }
        if (is_string($this->input('description'))) {
            $description = trim($this->input('description'));
            $donnees['description'] = $description === '' ? null : $description;
        }
        $this->merge($donnees);
    }

    protected function ignorerId(): ?int
    {
        return null;
    }
}
