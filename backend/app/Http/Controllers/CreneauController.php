<?php

namespace App\Http\Controllers;

use App\Http\Requests\RendezVous\PeriodeCreneauxRequest;
use App\Http\Resources\CreneauxResource;
use App\Models\Medecin;
use App\Models\Specialite;
use App\Services\CreneauService;

/**
 * Créneaux libres, consultables sans connexion (section 14).
 */
class CreneauController extends Controller
{
    public function __construct(private readonly CreneauService $creneaux) {}

    /** GET /api/specialites/{specialite}/creneaux?du=&au= : tous les médecins actifs de la spécialité. */
    public function parSpecialite(PeriodeCreneauxRequest $request, Specialite $specialite): CreneauxResource
    {
        [$du, $au] = $request->periode();

        return new CreneauxResource(['du' => $du, 'au' => $au, 'jours' => $this->creneaux->pourSpecialite($specialite, $du, $au)]);
    }

    /** GET /api/medecins/{medecin}/creneaux?du=&au= : aucun créneau pour un médecin désactivé. */
    public function parMedecin(PeriodeCreneauxRequest $request, Medecin $medecin): CreneauxResource
    {
        [$du, $au] = $request->periode();

        return new CreneauxResource(['du' => $du, 'au' => $au, 'jours' => $this->creneaux->pourMedecin($medecin, $du, $au)]);
    }
}
