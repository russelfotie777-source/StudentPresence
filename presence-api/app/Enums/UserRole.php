<?php

namespace App\Enums;

enum UserRole: string
{
    case Etudiant = 'Etudiant';
    case Delegue = 'Delegue';
    case Enseignant = 'Enseignant';
    case Admin = 'Admin';
}
