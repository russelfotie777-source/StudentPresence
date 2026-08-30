<?php

namespace App\Http\Requests\Requete;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequeteRequest extends FormRequest
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
        return [
            'seance_id' => ['required', 'exists:seances,id'],
            'description' => ['required', 'string', 'max:2000'],
            // Mêmes règles que l'ancienne app (requete.php) : 5 Mo, formats
            // image usuels + PDF.
            'preuve' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5000'],
        ];
    }
}
