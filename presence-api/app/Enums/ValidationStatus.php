<?php

namespace App\Enums;

enum ValidationStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
}
