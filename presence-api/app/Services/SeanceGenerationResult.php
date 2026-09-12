<?php

namespace App\Services;

use App\Models\Seance;
use Illuminate\Support\Collection;

/**
 * @param  Collection<int, Seance>  $created
 * @param  Collection<int, array{semaine_id: int, numero: int, date: string, reason: string}>  $skipped
 */
readonly class SeanceGenerationResult
{
    public function __construct(
        public Collection $created,
        public Collection $skipped,
    ) {}
}
