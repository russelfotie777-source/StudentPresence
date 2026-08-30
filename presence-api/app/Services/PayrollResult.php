<?php

namespace App\Services;

use Illuminate\Support\Collection;

readonly class PayrollResult
{
    /**
     * @param  Collection<int, PayrollLine>  $lines
     */
    public function __construct(
        public Collection $lines,
        public float $totalSalaire,
        public float $totalPenaliteRetard,
        public int $totalMinutes,
    ) {}
}
