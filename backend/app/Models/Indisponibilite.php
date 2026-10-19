<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Indisponibilite extends Model
{
    protected $table = 'indisponibilites';

    protected $fillable = [
        'medecin_id',
        'enregistree_par',
        'date_debut',
        'date_fin',
        'motif',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_id', 'user_id');
    }

    public function secretaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistree_par');
    }
}
