<?php

namespace App\Http\Controllers;

use App\Http\Requests\RendezVous\StoreRendezVousRequest;
use App\Http\Requests\RendezVous\UpdateRendezVousRequest;
use App\Http\Resources\RendezVousResource;
use App\Models\RendezVous;
use App\Services\RendezVousService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RendezVousController extends Controller
{
    public function __construct(private readonly RendezVousService $service) {}

    /** POST /api/rendez-vous (patient) */
    public function store(StoreRendezVousRequest $request): JsonResponse
    {
        $rendezVous = $this->service->reserver($request->user(), $request->medecin(), $request->dateHeure());

        return (new RendezVousResource($rendezVous))->response()->setStatusCode(201);
    }

    /** GET /api/mes-rendez-vous (patient) : les plus récents d'abord. */
    public function mesRendezVous(Request $request): AnonymousResourceCollection
    {
        $rendezVous = $request->user()->rendezVous()
            ->with(['medecin.user', 'medecin.specialite'])
            ->orderByDesc('date_heure')
            ->orderByDesc('id')
            ->get();

        return RendezVousResource::collection($rendezVous);
    }

    /** PATCH /api/rendez-vous/{rendezVous} (patient dans le délai, ou secrétaire) */
    public function update(UpdateRendezVousRequest $request, RendezVous $rendezVous): RendezVousResource
    {
        return new RendezVousResource($this->service->modifier($rendezVous, $request->medecin(), $request->dateHeure()));
    }

    /** POST /api/rendez-vous/{rendezVous}/annulation (patient dans le délai, ou secrétaire) */
    public function annuler(RendezVous $rendezVous): RendezVousResource
    {
        Gate::authorize('annuler', $rendezVous);

        return new RendezVousResource($this->service->annuler($rendezVous));
    }
}
