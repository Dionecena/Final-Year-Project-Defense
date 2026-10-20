<?php

namespace App\Http\Requests\Admin;

use App\Models\Specialite;

class UpdateSpecialiteRequest extends StoreSpecialiteRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('specialite'));
    }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function ignorerId(): ?int
    {
        /** @var Specialite $specialite */
        $specialite = $this->route('specialite');

        return $specialite->id;
    }
}
