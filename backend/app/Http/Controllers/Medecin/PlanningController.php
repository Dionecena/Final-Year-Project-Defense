<?php

namespace App\Http\Controllers\Medecin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretariat\MedecinController;
use App\Http\Resources\PlanningResource;
use App\Services\PlanningService;
use Illuminate\Http\Request;

/**
 * RG12 : le médecin consulte son propre planning, sans pouvoir le modifier.
 */
class PlanningController extends Controller
{
    public function __construct(private readonly PlanningService $planning) {}

    /** GET /api/medecin/planning?date=AAAA-MM-JJ */
    public function show(Request $request): PlanningResource
    {
        $medecin = $request->user()->medecin;
        abort_if($medecin === null, 404, 'Aucune fiche médecin n\'est associée à ce compte.');

        return new PlanningResource($this->planning->semaine($medecin, MedecinController::dateDemandee($request)));
    }
}
