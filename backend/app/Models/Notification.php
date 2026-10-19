<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des courriels envoyés au patient au sujet d'un rendez-vous.
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'rendez_vous_id',
        'objet',
        'contenu',
        'date_envoi',
    ];

    protected function casts(): array
    {
        return [
            'date_envoi' => 'datetime',
        ];
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }
}
