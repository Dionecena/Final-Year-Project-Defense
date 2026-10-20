<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodeStatistiquesRequest;
use App\Http\Resources\StatistiquesResource;
use App\Services\StatistiqueService;

/**
 * Tableau de bord statistique de l'administrateur (section 13).
 */
class StatistiqueController extends Controller
{
    public function __construct(private readonly StatistiqueService $statistiques) {}

    /** GET /api/admin/statistiques?du=AAAA-MM-JJ&au=AAAA-MM-JJ */
    public function index(PeriodeStatistiquesRequest $request): StatistiquesResource
    {
        return new StatistiquesResource($this->statistiques->calculer(...$request->periode()));
    }
}
