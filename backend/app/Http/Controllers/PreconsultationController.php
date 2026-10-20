<?php

namespace App\Http\Controllers;

use App\Http\Resources\PreconsultationResource;
use App\Models\Preconsultation;
use App\Models\RendezVous;
use Illuminate\Support\Facades\Gate;

class PreconsultationController extends Controller
{
    /** GET /api/rendez-vous/{rendezVous}/preconsultation : médecin du rendez-vous ou patient concerné (RG10). */
    public function show(RendezVous $rendezVous): PreconsultationResource
    {
        // Autorisation avant tout : un tiers ne doit pas apprendre si une préconsultation existe.
        Gate::authorize('view', [Preconsultation::class, $rendezVous]);

        $preconsultation = $rendezVous->preconsultation()->with('specialiteSuggeree')->first();
        abort_if($preconsultation === null, 404, 'Aucune préconsultation n\'a été saisie pour ce rendez-vous.');

        return new PreconsultationResource($preconsultation);
    }
}
