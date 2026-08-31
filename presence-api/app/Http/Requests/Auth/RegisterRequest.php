<?php

namespace App\Http\Requests\Auth;

use App\Enums\FormationType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $needsAcademicFields = in_array($this->input('role'), [UserRole::Etudiant->value, UserRole::Delegue->value], true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([UserRole::Etudiant->value, UserRole::Delegue->value, UserRole::Enseignant->value])],
            // FM ("formation migrante") n'est jamais un choix à l'inscription :
            // c'est un statut accordé uniquement par l'admin à un étudiant FA
            // qui demande à suivre les cours en FI (voir superprotect, à venir).
            'formation' => [Rule::requiredIf($needsAcademicFields), Rule::in([FormationType::FI->value, FormationType::FA->value])],
            'salle_id' => [Rule::requiredIf($needsAcademicFields), 'exists:salles,id'],
            'niveau_id' => [Rule::requiredIf($needsAcademicFields), 'exists:niveaux,id'],
            'filiere_id' => [Rule::requiredIf($needsAcademicFields), 'exists:filieres,id'],
        ];
    }
}
