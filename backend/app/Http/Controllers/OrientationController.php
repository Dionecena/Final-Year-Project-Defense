<?php

namespace App\Http\Controllers;

use App\Http\Requests\Orientation\VerificationOrientationRequest;
use App\Http\Resources\CreneauResource;
use App\Http\Resources\SpecialiteResource;
use App\Services\OrientationService;
use Illuminate\Http\JsonResponse;

/**
 * Suggestion d'orientation indicative (section 8.3, RG03, RG04). Rien n'est enregistré ici :
 * la préconsultation l'est avec la réservation.
 */
class OrientationController extends Controller
{
    public function __construct(private readonly OrientationService $orientation) {}

    /** POST /api/orientation/verification (patient) : { specialite_id, motif, symptomes } */
    public function verifier(VerificationOrientationRequest $request): JsonResponse
    {
        $resultat = $this->orientation->verifier(
            $request->specialite(),
            $request->validated('motif'),
            $request->validated('symptomes'),
        );

        return response()->json(['data' => [
            'suggestion' => $resultat === null ? null : [
                'specialite' => new SpecialiteResource($resultat['specialite']),
                'creneaux' => CreneauResource::collection($resultat['creneaux']),
            ],
        ]]);
    }
}
