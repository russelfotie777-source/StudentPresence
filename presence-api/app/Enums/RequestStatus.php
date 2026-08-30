<?php

namespace App\Enums;

enum RequestStatus: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Rejetee = 'rejetee';
}
