<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Services\ComptesParAppareil;
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

    /** Nombre maximal de tentatives par minute depuis une même adresse IP, tous comptes confondus. */
    private const TENTATIVES_MAX_IP = 20;

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

        // Toute tentative compte pour l'adresse IP : un script qui change d'adresse électronique est freiné.
        RateLimiter::hit($this->cleLimiteIp(), 60);

        $user = User::where('email', $this->input('email'))->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password) || ! $user->actif) {
            RateLimiter::hit($this->cleLimite(), 60);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->cleLimite());

        // Au plus 3 comptes patients différents par appareil et par heure (le personnel n'est pas limité).
        if ($user->role === Role::Patient) {
            $appareil = app(ComptesParAppareil::class);
            if (! $appareil->autorise($this->ip(), $user->id)) {
                throw ValidationException::withMessages(['email' => ComptesParAppareil::MESSAGE])->status(429);
            }
            $appareil->enregistrer($this->ip(), $user->id);
        }

        return $user;
    }

    private function verifierLimiteTentatives(): void
    {
        $cle = match (true) {
            RateLimiter::tooManyAttempts($this->cleLimite(), self::TENTATIVES_MAX) => $this->cleLimite(),
            RateLimiter::tooManyAttempts($this->cleLimiteIp(), self::TENTATIVES_MAX_IP) => $this->cleLimiteIp(),
            default => null,
        };

        if ($cle === null) {
            return;
        }

        event(new Lockout($this));

        $secondes = RateLimiter::availableIn($cle);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $secondes]),
        ])->status(429);
    }

    private function cleLimite(): string
    {
        return 'connexion|'.$this->input('email').'|'.$this->ip();
    }

    private function cleLimiteIp(): string
    {
        return 'connexion-ip|'.$this->ip();
    }
}
