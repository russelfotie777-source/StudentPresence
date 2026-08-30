<?php

namespace App\Services;

use App\Models\Seance;

readonly class PayrollLine
{
    public function __construct(
        public Seance $seance,
        public int $retardMinutes,
        public int $dureeMinutes,
        public float $tarifPlein,
        public float $salaire,
        public float $penaliteRetard,
    ) {}
}
