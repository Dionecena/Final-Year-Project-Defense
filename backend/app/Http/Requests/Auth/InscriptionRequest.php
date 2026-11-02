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
            'email' => [...$this->regleEmail(), ...(config('clinique.verifier_domaine_email') ? ['email:rfc,dns'] : [])],
            'telephone' => $this->regleTelephone(),
            'password' => $this->reglePassword(),
            // Politique de confidentialité et conditions d'utilisation (loi n° 2008-12).
            'consentement' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Cette adresse électronique n\'est pas valide : vérifiez-la, en particulier ce qui suit « @ ».',
            'consentement.accepted' => 'Vous devez accepter la politique de confidentialité et les conditions d\'utilisation.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliserChamps();
    }
}
