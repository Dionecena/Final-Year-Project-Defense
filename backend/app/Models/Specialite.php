<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialite extends Model
{
    protected $table = 'specialites';

    protected $fillable = [
        'libelle',
        'description',
    ];

    public function medecins(): HasMany
    {
        return $this->hasMany(Medecin::class);
    }

    /** RG11 : symptômes saisis dans un champ libre et court (config('preconsultation.saisie_restreinte')). */
    public function aSaisieRestreinte(): bool
    {
        return in_array($this->libelle, config('preconsultation.saisie_restreinte', []), true);
    }

    /** RG11 : longueur maximale du champ « symptômes » pour cette spécialité. */
    public function maxSymptomes(): int
    {
        return (int) config($this->aSaisieRestreinte() ? 'preconsultation.max_symptomes_restreinte' : 'preconsultation.max_symptomes');
    }
}
