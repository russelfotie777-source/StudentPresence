<?php

namespace Tests\Feature;

use App\Models\Parametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Sanctum n'expire aucun jeton par défaut, et le back-office conserve le sien
 * dans le localStorage du navigateur. Sans échéance, un jeton récupéré sur un
 * poste partagé garderait indéfiniment les droits les plus élevés de
 * l'application.
 *
 * L'échéance est posée par jeton : l'appliquer globalement déconnecterait les
 * étudiants en pleine journée de cours, pour un risque bien moindre.
 */
class AdminSessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    private function connexion(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'phone' => $user->phone,
            'password' => 'password123',
        ])->assertOk()->json('token');
    }

    public function test_admin_token_expires(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password123')]);

        $this->connexion($admin);

        $jeton = $admin->tokens()->latest('id')->first();

        $this->assertNotNull($jeton->expires_at, "Le jeton d'administration doit porter une échéance.");
        $this->assertEqualsWithDelta(
            config('presence.admin_session_hours'),
            now()->diffInHours($jeton->expires_at, absolute: true),
            1,
        );
    }

    public function test_other_roles_keep_a_token_without_expiry(): void
    {
        // Le facial est désactivé ici pour obtenir un jeton complet : sinon le
        // jeton émis serait le "face-pending" à 15 minutes, dont l'échéance
        // relève d'un tout autre mécanisme.
        Parametre::setFaceAuthRoles([]);

        // Un étudiant déconnecté en plein cours ne pourrait plus pointer :
        // l'échéance ne doit toucher que l'administration.
        $enseignant = User::factory()->enseignant()->create(['password' => bcrypt('password123')]);

        $this->connexion($enseignant);

        $this->assertNull($enseignant->tokens()->latest('id')->first()->expires_at);
    }

    public function test_an_expired_admin_token_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $jeton = $admin->createToken('presence-app', ['*'], now()->subMinute())->plainTextToken;

        Auth::forgetGuards();
        $this->withToken($jeton)->getJson('/api/validations')->assertUnauthorized();
    }

    public function test_a_valid_admin_token_still_works(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password123')]);

        $jeton = $this->connexion($admin);

        Auth::forgetGuards();
        $this->withToken($jeton)->getJson('/api/validations')->assertOk();
    }
}
