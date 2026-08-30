<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Remplace le compte admin partagé de l'ancienne app (identifiants
 * "admin123"/"passadmin123" en dur dans le code source) par de vrais
 * comptes nommés, créés un par un via cette commande — pas de formulaire
 * d'inscription public pour le rôle Admin.
 */
#[Signature('app:make-admin {name} {phone} {password}')]
#[Description('Crée un compte administrateur (aucune inscription publique pour ce rôle).')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $validator = Validator::make($this->arguments(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->argument('name'),
            'phone' => $this->argument('phone'),
            'password' => Hash::make($this->argument('password')),
            'role' => UserRole::Admin,
            'validation_status' => ValidationStatus::Approved,
        ]);

        $this->info("Compte admin créé : {$user->name} ({$user->phone}).");

        return self::SUCCESS;
    }
}
