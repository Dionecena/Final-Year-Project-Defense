<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSpecialiteRequest;
use App\Http\Requests\Admin\UpdateSpecialiteRequest;
use App\Http\Resources\SpecialiteResource;
use App\Models\Specialite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Gestion des spécialités par l'administrateur. Pas de suppression (section 14).
 */
class SpecialiteController extends Controller
{
    /** GET /api/admin/specialites */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Specialite::class);

        return SpecialiteResource::collection(Specialite::withCount('medecins')->orderBy('libelle')->get());
    }

    /** POST /api/admin/specialites */
    public function store(StoreSpecialiteRequest $request): JsonResponse
    {
        $specialite = Specialite::create($request->validated());

        return (new SpecialiteResource($specialite))->response()->setStatusCode(201);
    }

    /** PATCH /api/admin/specialites/{specialite} */
    public function update(UpdateSpecialiteRequest $request, Specialite $specialite): SpecialiteResource
    {
        $specialite->update($request->validated());

        return new SpecialiteResource($specialite);
    }
}
