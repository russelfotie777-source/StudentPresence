<?php

namespace App\Http\Requests\Auth;

use App\Enums\FormationType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'formation' => [Rule::requiredIf($needsAcademicFields), Rule::in(array_column(FormationType::cases(), 'value'))],
            'salle_id' => [Rule::requiredIf($needsAcademicFields), 'exists:salles,id'],
            'niveau_id' => [Rule::requiredIf($needsAcademicFields), 'exists:niveaux,id'],
            'filiere_id' => [Rule::requiredIf($needsAcademicFields), 'exists:filieres,id'],
        ];
    }

    /**
     * Règle métier portée depuis l'ancienne app : une salle "FM" (étudiants
     * migrants rattachés à une salle FI) est plafonnée à 50 inscrits.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('role') === UserRole::Etudiant->value && $this->input('formation') === FormationType::FM->value) {
                $count = User::query()
                    ->where('salle_id', $this->input('salle_id'))
                    ->where('formation', FormationType::FM->value)
                    ->count();

                if ($count >= 50) {
                    $validator->errors()->add('salle_id', 'La salle de classe est pleine pour les étudiants "FM".');
                }
            }
        });
    }
}
