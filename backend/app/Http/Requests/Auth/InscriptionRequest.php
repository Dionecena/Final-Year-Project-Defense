<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ReglesUtilisateur;
use Illuminate\Foundation\Http\FormRequest;

class InscriptionRequest extends FormRequest
{
    use ReglesUtilisateur;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => $this->regleNom(),
            'prenom' => $this->regleNom(),
            'email' => $this->regleEmail(),
            'telephone' => $this->regleTelephone(),
            'password' => $this->reglePassword(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliserChamps();
    }
}
