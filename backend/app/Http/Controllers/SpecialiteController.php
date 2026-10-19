<?php

namespace App\Http\Controllers;

use App\Http\Resources\SpecialiteResource;
use App\Models\Specialite;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpecialiteController extends Controller
{
    /** GET /api/specialites (public) : liste avec les jours de présence. */
    public function index(): AnonymousResourceCollection
    {
        return SpecialiteResource::collection(Specialite::orderBy('libelle')->get());
    }
}
