<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUtilisateurRequest;
use App\Http\Requests\Admin\UpdateUtilisateurRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use App\Services\UtilisateurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UtilisateurController extends Controller
{
    public function __construct(private readonly UtilisateurService $service) {}

    /** GET /api/admin/utilisateurs?role=&actif=&recherche= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filtres = $request->validate([
            'role' => ['nullable', Rule::in(['patient', 'secretaire', 'medecin', 'administrateur'])],
            'actif' => ['nullable', 'boolean'],
            'recherche' => ['nullable', 'string', 'max:100'],
        ]);

        $utilisateurs = User::query()
            ->with('medecin.specialite')
            ->when($filtres['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when(isset($filtres['actif']), fn ($q) => $q->where('actif', (bool) $filtres['actif']))
            ->when($filtres['recherche'] ?? null, function ($q, $texte) {
                $motif = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($texte)).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(nom) LIKE ?', [$motif])
                    ->orWhereRaw('LOWER(prenom) LIKE ?', [$motif])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$motif]));
            })
            ->orderBy('nom')->orderBy('prenom')
            ->paginate(20)
            ->withQueryString();

        return UtilisateurResource::collection($utilisateurs);
    }

    /** POST /api/admin/utilisateurs */
    public function store(StoreUtilisateurRequest $request): JsonResponse
    {
        $user = $this->service->creer($request->validated());

        return (new UtilisateurResource($user))->response()->setStatusCode(201);
    }

    /** GET /api/admin/utilisateurs/{utilisateur} */
    public function show(User $utilisateur): UtilisateurResource
    {
        Gate::authorize('view', $utilisateur);

        return new UtilisateurResource($utilisateur->load('medecin.specialite'));
    }

    /** PATCH /api/admin/utilisateurs/{utilisateur} */
    public function update(UpdateUtilisateurRequest $request, User $utilisateur): UtilisateurResource
    {
        return new UtilisateurResource($this->service->modifier($utilisateur, $request->validated()));
    }

    /** POST /api/admin/utilisateurs/{utilisateur}/desactivation */
    public function desactiver(User $utilisateur): UtilisateurResource
    {
        Gate::authorize('desactiver', $utilisateur);

        return new UtilisateurResource($this->service->desactiver($utilisateur));
    }
}
