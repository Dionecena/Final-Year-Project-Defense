<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plage hebdomadaire de consultation d'un médecin. Jamais modifiée ni supprimée (RG14).
 */
class Disponibilite extends Model
{
    protected $table = 'disponibilites';

    protected $fillable = [
        'medecin_id',
        'jour_semaine',
        'heure_debut',
        'heure_fin',
        'duree_creneau',
    ];

    protected function casts(): array
    {
        return [
            'jour_semaine' => 'integer',
            'duree_creneau' => 'integer',
        ];
    }

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_id', 'user_id');
    }
}
