<?php

namespace App\Http\Controllers\Secretariat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Secretariat\StoreDisponibiliteRequest;
use App\Http\Resources\DisponibiliteResource;
use App\Http\Resources\MedecinResource;
use App\Http\Resources\PlanningResource;
use App\Models\Medecin;
use App\Services\PlanningService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Plannings des médecins côté secrétariat. RG14 : seul l'ajout d'une disponibilité existe,
 * aucune route de modification ni de suppression.
 */
class MedecinController extends Controller
{
    public function __construct(private readonly PlanningService $planning) {}

    /** GET /api/medecins : liste pour le choix du médecin (route ajoutée, validée par l'auteur). */
    public function index(): AnonymousResourceCollection
    {
        $medecins = Medecin::query()
            ->with(['user', 'specialite'])
            ->join('users', 'users.id', '=', 'medecins.user_id')
            ->orderByDesc('users.actif')
            ->orderBy('users.nom')
            ->orderBy('users.prenom')
            ->select('medecins.*')
            ->get();

        return MedecinResource::collection($medecins);
    }

    /** GET /api/medecins/{medecin}/planning?date=AAAA-MM-JJ */
    public function planning(Request $request, Medecin $medecin): PlanningResource
    {
        return new PlanningResource($this->planning->semaine($medecin, self::dateDemandee($request)));
    }

    /** POST /api/medecins/{medecin}/disponibilites */
    public function ajouterDisponibilite(StoreDisponibiliteRequest $request, Medecin $medecin): JsonResponse
    {
        $disponibilite = $medecin->disponibilites()->create($request->validated());

        return (new DisponibiliteResource($disponibilite))->response()->setStatusCode(201);
    }

    /** Date de la semaine demandée (paramètre « date »), aujourd'hui par défaut. */
    public static function dateDemandee(Request $request): CarbonImmutable
    {
        $valide = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return isset($valide['date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $valide['date'])
            : CarbonImmutable::today();
    }
}
