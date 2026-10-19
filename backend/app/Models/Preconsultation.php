<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Données de santé : lisibles seulement par le médecin du rendez-vous et par le patient (RG10).
 */
class Preconsultation extends Model
{
    protected $table = 'preconsultations';

    public $timestamps = false;

    protected $fillable = [
        'rendez_vous_id',
        'motif',
        'symptomes',
        'specialite_suggeree_id',
        'date_saisie',
    ];

    protected function casts(): array
    {
        return [
            'date_saisie' => 'datetime',
        ];
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }

    public function specialiteSuggeree(): BelongsTo
    {
        return $this->belongsTo(Specialite::class, 'specialite_suggeree_id');
    }
}
