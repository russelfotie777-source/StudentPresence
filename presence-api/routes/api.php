<?php

use App\Http\Controllers\Api\AccountValidationController;
use App\Http\Controllers\Api\AttendanceStatsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseTemplateController;
use App\Http\Controllers\Api\EnseignantController;
use App\Http\Controllers\Api\FiliereController;
use App\Http\Controllers\Api\MatiereController;
use App\Http\Controllers\Api\NiveauController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RequeteController;
use App\Http\Controllers\Api\SalleController;
use App\Http\Controllers\Api\SeanceController;
use App\Http\Controllers\Api\SemaineController;
use App\Http\Controllers\Api\SessionHistoryController;
use App\Http\Controllers\Api\StudentSearchController;
use App\Http\Controllers\Api\TarifHeureController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/request-validation', [AuthController::class, 'requestValidation']);
    });
});

// Lecture publique du catalogue (niveaux/filières/salles) : nécessaire pour
// peupler le formulaire d'inscription (register.php le faisait déjà côté
// ancienne app, sans authentification). Uniquement index/show — la
// création/modification reste réservée à l'admin ci-dessous.
Route::middleware([])->group(function () {
    Route::apiResource('niveaux', NiveauController::class)->only(['index', 'show']);
    Route::apiResource('filieres', FiliereController::class)->only(['index', 'show']);
    Route::apiResource('salles', SalleController::class)->only(['index', 'show']);
});

// Catalogue académique + planification — réservé au back-office admin.
Route::middleware(['auth:sanctum', 'validated', 'role:Admin'])->group(function () {
    Route::apiResource('niveaux', NiveauController::class)->except(['index', 'show']);
    Route::apiResource('filieres', FiliereController::class)->except(['index', 'show']);
    Route::apiResource('salles', SalleController::class)->except(['index', 'show']);
    Route::apiResource('matieres', MatiereController::class);

    Route::apiResource('semaines', SemaineController::class);
    Route::post('/semaines/generate-semester', [SemaineController::class, 'generateSemester']);

    Route::apiResource('course-templates', CourseTemplateController::class);
    Route::post('/course-templates/{courseTemplate}/generate', [CourseTemplateController::class, 'generate']);

    Route::get('/enseignants', [EnseignantController::class, 'index']);

    Route::get('/tarifs-heures', [TarifHeureController::class, 'index']);
    Route::put('/tarifs-heures/{niveau}', [TarifHeureController::class, 'update']);

    Route::get('/validations', [AccountValidationController::class, 'index']);
    Route::post('/validations/{user}/approve', [AccountValidationController::class, 'approve']);
    Route::post('/validations/{user}/reject', [AccountValidationController::class, 'reject']);

    Route::get('/requetes', [RequeteController::class, 'index']);
    Route::post('/requetes/{requete}/process', [RequeteController::class, 'process']);

    Route::get('/payroll/teachers/{teacher}', [PayrollController::class, 'forTeacher']);

    Route::get('/historique-seances', [SessionHistoryController::class, 'index']);
});

// Cœur métier : présence — accessible à Étudiant/Délégué/Enseignant selon
// l'action (chaque contrôleur vérifie le rôle effectif en interne).
Route::middleware(['auth:sanctum', 'validated'])->group(function () {
    Route::get('/seances/today', [SeanceController::class, 'today']);
    Route::get('/me/attendance-stats', [AttendanceStatsController::class, 'me']);
    Route::post('/seances/{seance}/mark-delegue', [SeanceController::class, 'markDelegue']);
    Route::post('/seances/{seance}/mark-prof', [SeanceController::class, 'markProf']);
    Route::post('/seances/{seance}/push', [SeanceController::class, 'push']);

    Route::post('/seances/{seance}/position', [PositionController::class, 'store']);

    Route::post('/seances/{seance}/check-in', [PresenceController::class, 'checkIn']);
    Route::get('/seances/{seance}/roster', [PresenceController::class, 'roster']);
    Route::post('/seances/{seance}/confirm-roster', [PresenceController::class, 'confirmRoster']);
    Route::get('/seances/{seance}/presence-list.pdf', [PdfController::class, 'presenceList']);

    Route::get('/payroll/me', [PayrollController::class, 'me']);

    Route::post('/requetes', [RequeteController::class, 'store']);
    Route::get('/requetes/mine', [RequeteController::class, 'mine']);

    Route::get('/students/search', [StudentSearchController::class, 'index']);
    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::post('/promotions', [PromotionController::class, 'store']);
});
