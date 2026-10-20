<?php

use App\Http\Controllers\Admin\SpecialiteController as AdminSpecialiteController;
use App\Http\Controllers\Admin\StatistiqueController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreneauController;
use App\Http\Controllers\Medecin\PlanningController;
use App\Http\Controllers\OrientationController;
use App\Http\Controllers\PreconsultationController;
use App\Http\Controllers\RendezVousController;
use App\Http\Controllers\Secretariat\MedecinController;
use App\Http\Controllers\Secretariat\RendezVousController as SecretariatRendezVousController;
use App\Http\Controllers\SpecialiteController;
use Illuminate\Support\Facades\Route;

/*
| Routes de l'API (préfixe /api). Les routes /login et /logout sont dans routes/web.php.
|
| RG01 : tout ce qui n'est pas l'accueil public, la liste des spécialités, l'inscription
| et la connexion exige une authentification (groupe auth:sanctum ci-dessous).
*/

// --- Routes publiques -------------------------------------------------------------
Route::post('/inscription', [AuthController::class, 'inscription']);
Route::get('/specialites', [SpecialiteController::class, 'index']);
// Créneaux libres (section 14 : publics).
Route::get('/specialites/{specialite}/creneaux', [CreneauController::class, 'parSpecialite']);
Route::get('/medecins/{medecin}/creneaux', [CreneauController::class, 'parMedecin']);

// --- Routes authentifiées ---------------------------------------------------------
Route::middleware(['auth:sanctum', 'compte.actif'])->group(function () {

    Route::get('/moi', [AuthController::class, 'moi']);

    // Patient : vérification d'orientation (section 8.3), réservation avec la préconsultation
    // facultative (RG02) et consultation de ses rendez-vous.
    Route::middleware('role:patient')->group(function () {
        Route::post('/orientation/verification', [OrientationController::class, 'verifier']);
        Route::post('/rendez-vous', [RendezVousController::class, 'store']);
        Route::get('/mes-rendez-vous', [RendezVousController::class, 'mesRendezVous']);
    });

    // Patient (le sien, avant le délai : RG08) ou secrétaire, contrôlé par RendezVousPolicy.
    Route::middleware('role:patient,secretaire')->group(function () {
        Route::patch('/rendez-vous/{rendezVous}', [RendezVousController::class, 'update']);
        Route::post('/rendez-vous/{rendezVous}/annulation', [RendezVousController::class, 'annuler']);
    });

    // RG10 : contenu de la préconsultation, pour le médecin du rendez-vous ou le patient concerné
    // (PreconsultationPolicy). Ni la secrétaire ni l'administrateur n'y ont accès.
    Route::middleware('role:patient,medecin')->group(function () {
        Route::get('/rendez-vous/{rendezVous}/preconsultation', [PreconsultationController::class, 'show']);
    });

    // Secrétaire : plannings des médecins. RG14 : aucune route PUT, PATCH ni DELETE
    // sur les disponibilités, tout changement passe par une indisponibilité.
    Route::middleware('role:secretaire')->group(function () {
        Route::get('/medecins', [MedecinController::class, 'index']);
        Route::get('/secretariat/rendez-vous', [SecretariatRendezVousController::class, 'index']);
        Route::get('/medecins/{medecin}/planning', [MedecinController::class, 'planning']);
        Route::post('/medecins/{medecin}/disponibilites', [MedecinController::class, 'ajouterDisponibilite']);
        // RG09 : les rendez-vous touchés passent à « à reprogrammer » ; la secrétaire les traite
        // ensuite par PATCH /rendez-vous/{id} (reprogrammer) ou POST …/annulation (annuler).
        Route::post('/medecins/{medecin}/indisponibilites', [MedecinController::class, 'ajouterIndisponibilite']);
    });

    // Médecin : son propre planning, en lecture seule (RG12).
    Route::middleware('role:medecin')->prefix('medecin')->group(function () {
        Route::get('/planning', [PlanningController::class, 'show']);
    });

    // Administrateur
    Route::middleware('role:administrateur')->prefix('admin')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
        Route::get('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'show']);
        Route::patch('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update']);
        Route::post('/utilisateurs/{utilisateur}/desactivation', [UtilisateurController::class, 'desactiver']);

        // Spécialités : pas de route de suppression (section 14).
        Route::get('/specialites', [AdminSpecialiteController::class, 'index']);
        Route::post('/specialites', [AdminSpecialiteController::class, 'store']);
        Route::patch('/specialites/{specialite}', [AdminSpecialiteController::class, 'update']);

        // Statistiques : comptes agrégés, sans donnée nominative ni contenu de préconsultation.
        Route::get('/statistiques', [StatistiqueController::class, 'index']);
    });

});
