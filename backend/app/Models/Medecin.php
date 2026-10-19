<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Données propres à la sous-classe Medecin (table medecins, clé primaire user_id).
 */
class Medecin extends Model
{
    protected $table = 'medecins';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'specialite_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function specialite(): BelongsTo
    {
        return $this->belongsTo(Specialite::class);
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class, 'medecin_id', 'user_id');
    }

    public function indisponibilites(): HasMany
    {
        return $this->hasMany(Indisponibilite::class, 'medecin_id', 'user_id');
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class, 'medecin_id', 'user_id');
    }
}
