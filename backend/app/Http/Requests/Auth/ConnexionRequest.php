<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConnexionRequest extends FormRequest
{
    /** Nombre maximal de tentatives par minute, par adresse électronique et adresse IP. */
    private const TENTATIVES_MAX = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * Vérifie les identifiants et l'état du compte. Le même message générique est renvoyé
     * pour un compte inconnu, un mauvais mot de passe ou un compte désactivé.
     *
     * @throws ValidationException
     */
    public function utilisateurAuthentifie(): User
    {
        $this->verifierLimiteTentatives();

        $user = User::where('email', $this->input('email'))->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password) || ! $user->actif) {
            RateLimiter::hit($this->cleLimite(), 60);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->cleLimite());

        return $user;
    }

    private function verifierLimiteTentatives(): void
    {
        if (! RateLimiter::tooManyAttempts($this->cleLimite(), self::TENTATIVES_MAX)) {
            return;
        }

        event(new Lockout($this));

        $secondes = RateLimiter::availableIn($this->cleLimite());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $secondes]),
        ])->status(429);
    }

    private function cleLimite(): string
    {
        return 'connexion|'.$this->input('email').'|'.$this->ip();
    }
}
