<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un code à six chiffres envoyé par e-mail, pour confirmer une adresse ou
 * réinitialiser un mot de passe. Voir App\Services\CodesEmail.
 */
#[Fillable(['user_id', 'usage', 'email', 'code_hash', 'expire_le', 'tentatives'])]
#[Hidden(['code_hash'])]
class CodeEmail extends Model
{
    public const VERIFICATION = 'verification';

    public const REINITIALISATION = 'reinitialisation';

    protected $table = 'codes_email';

    protected function casts(): array
    {
        return ['expire_le' => 'datetime', 'tentatives' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expire(): bool
    {
        return $this->expire_le->isPast();
    }
}
