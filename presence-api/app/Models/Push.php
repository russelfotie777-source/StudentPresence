<?php

namespace App\Models;

use App\Enums\PushStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seance_id', 'etudiants_presents', 'status'])]
class Push extends Model
{
    protected function casts(): array
    {
        return ['status' => PushStatus::class];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }
}
