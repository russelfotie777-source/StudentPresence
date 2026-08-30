<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseTemplateController;
use App\Http\Controllers\Api\FiliereController;
use App\Http\Controllers\Api\MatiereController;
use App\Http\Controllers\Api\NiveauController;
use App\Http\Controllers\Api\SalleController;
use App\Http\Controllers\Api\SemaineController;
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

// Catalogue académique + planification — réservé au back-office admin.
Route::middleware(['auth:sanctum', 'validated', 'role:Admin'])->group(function () {
    Route::apiResource('niveaux', NiveauController::class);
    Route::apiResource('filieres', FiliereController::class);
    Route::apiResource('salles', SalleController::class);
    Route::apiResource('matieres', MatiereController::class);

    Route::apiResource('semaines', SemaineController::class);
    Route::post('/semaines/generate-semester', [SemaineController::class, 'generateSemester']);

    Route::apiResource('course-templates', CourseTemplateController::class);
    Route::post('/course-templates/{courseTemplate}/generate', [CourseTemplateController::class, 'generate']);
});
