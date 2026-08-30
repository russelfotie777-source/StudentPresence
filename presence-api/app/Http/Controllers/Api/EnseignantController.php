<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;

class EnseignantController extends Controller
{
    /**
     * Liste des enseignants validés — peuple le formulaire admin de création
     * de course_template (choix de l'enseignant).
     */
    public function index()
    {
        return User::query()
            ->where('role', UserRole::Enseignant->value)
            ->where('validation_status', ValidationStatus::Approved->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
