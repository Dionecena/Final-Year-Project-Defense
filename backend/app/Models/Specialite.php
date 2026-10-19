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
}
