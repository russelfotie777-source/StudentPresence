<?php

namespace App\Services;

use App\Models\Seance;

readonly class PlanningUpdateResult
{
    public function __construct(
        public Seance $seance,
        public int $updatedCount,
        public int $preservedCount,
    ) {}
}
