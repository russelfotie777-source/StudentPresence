<?php

namespace App\Http\Requests;

use App\Models\Parametre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Les options d'une liste de présence hebdomadaire, communes au PDF et au
 * classeur Excel : la semaine, et ce qui s'imprime en en-tête quand la
 * valeur déduite ne convient pas.
 */
class OptionsListeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semaine_id' => ['required', 'integer', 'exists:semaines,id'],
            'semestre' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:6'],
            'annee' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{4}$/'],
            // Sans valeur : le réglage enregistré par l'admin (Parametre::symbolesPresence).
            'symboles' => ['sometimes', 'nullable', Rule::in(Parametre::SYMBOLES_PRESENCE_CHOIX)],
            // Vrai : seuls les étudiants migrants (FM) de la salle figurent sur la liste.
            'migrants' => ['sometimes', 'boolean'],
        ];
    }
}
