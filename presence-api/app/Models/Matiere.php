<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'code'])]
class Matiere extends Model
{
    public function courseTemplates(): HasMany
    {
        return $this->hasMany(CourseTemplate::class);
    }
}
