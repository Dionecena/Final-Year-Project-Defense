<?php

namespace App\Http\Controllers\Secretariat;

use App\Enums\StatutRdv;
use App\Http\Controllers\Controller;
use App\Http\Resources\RendezVousResource;
use App\Models\RendezVous;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Liste des rendez-vous du secrétariat, filtrable par médecin, date et statut.
 */
class RendezVousController extends Controller
{
    /** GET /api/secretariat/rendez-vous?medecin_id=&date=AAAA-MM-JJ&statut= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'medecin_id' => ['nullable', 'integer', 'exists:medecins,user_id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'statut' => ['nullable', Rule::enum(StatutRdv::class)],
        ]);

        $rendezVous = RendezVous::query()
            ->with(['medecin.user', 'medecin.specialite', 'patient'])
            ->when($filtres['medecin_id'] ?? null, fn ($q, $id) => $q->where('medecin_id', $id))
            ->when($filtres['date'] ?? null, function ($q, $date) {
                $jour = CarbonImmutable::createFromFormat('!Y-m-d', $date);
                $q->where('date_heure', '>=', $jour)->where('date_heure', '<', $jour->addDay());
            })
            ->when($filtres['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->orderBy('date_heure')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return RendezVousResource::collection($rendezVous);
    }
}
