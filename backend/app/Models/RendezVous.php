<?php

namespace App\Models;

use App\Enums\StatutRdv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RendezVous extends Model
{
    protected $table = 'rendez_vous';

    protected $fillable = [
        'patient_id',
        'medecin_id',
        'date_heure',
        'statut',
    ];

    protected $attributes = [
        'statut' => 'confirme',
    ];

    protected function casts(): array
    {
        return [
            'date_heure' => 'datetime',
            'statut' => StatutRdv::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_id', 'user_id');
    }

    public function preconsultation(): HasOne
    {
        return $this->hasOne(Preconsultation::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Rendez-vous qui occupent leur créneau (confirme ou a_reprogrammer). */
    public function scopeActifs(Builder $query): Builder
    {
        return $query->whereIn('statut', StatutRdv::actifs());
    }
}
