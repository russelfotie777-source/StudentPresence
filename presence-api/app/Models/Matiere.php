<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'code'])]
class Matiere extends Model
{
    use HasFactory;

    public function courseTemplates(): HasMany
    {
        return $this->hasMany(CourseTemplate::class);
    }
}
