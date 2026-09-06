<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Parametre;
use App\Models\PromotionTemporaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réglage admin des grades soumis au second facteur facial. Son rôle
 * opérationnel : pouvoir débloquer immédiatement un compte qui n'arrive pas
 * à inscrire son visage, sans redéploiement.
 */
class FaceAuthSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_covers_etudiant_delegue_and_enseignant(): void
    {
        $this->assertEqualsCanonicalizing(
            [UserRole::Etudiant->value, UserRole::Delegue->value, UserRole::Enseignant->value],
            Parametre::faceAuthRoles(),
        );
    }

    public function test_admin_can_read_the_setting(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/parametres/face-auth')
            ->assertOk()
            ->assertJsonPath('roles', Parametre::FACE_AUTH_ROLES_DEFAUT);
    }

    public function test_admin_can_disable_face_auth_for_a_role(): void
    {
        $admin = User::factory()->admin()->create();
        $enseignant = User::factory()->enseignant()->create(['password' => bcrypt('password123')]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/parametres/face-auth', [
                'roles' => [UserRole::Etudiant->value, UserRole::Delegue->value],
            ])
            ->assertOk();

        // L'enseignant retiré du réglage se connecte de nouveau sans facial.
        $this->postJson('/api/auth/login', [
            'phone' => $enseignant->phone,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('requires_face', false);
    }

    public function test_admin_can_disable_face_auth_entirely(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('password123')]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/parametres/face-auth', ['roles' => []])
            ->assertOk()
            ->assertJsonPath('roles', []);

        $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('requires_face', false);
    }

    public function test_admin_role_cannot_be_added_to_the_setting(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/parametres/face-auth', [
                'roles' => [UserRole::Etudiant->value, UserRole::Admin->value],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles.1');
    }

    /**
     * Même si une valeur "Admin" arrivait en base par un autre chemin
     * (import, écriture manuelle), elle doit rester sans effet.
     */
    public function test_admin_is_filtered_out_even_if_stored(): void
    {
        Parametre::updateOrCreate(
            ['cle' => Parametre::FACE_AUTH_ROLES],
            ['valeur' => [UserRole::Etudiant->value, UserRole::Admin->value]],
        );

        $this->assertSame([UserRole::Etudiant->value], Parametre::faceAuthRoles());

        $admin = User::factory()->admin()->create();
        $this->assertFalse($admin->requiresFaceAuth());
    }

    public function test_non_admin_cannot_read_or_change_the_setting(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/parametres/face-auth')
            ->assertForbidden();

        $this->actingAs($enseignant, 'sanctum')
            ->putJson('/api/parametres/face-auth', ['roles' => []])
            ->assertForbidden();
    }

    /**
     * Le facial suit le grade réel, pas le grade effectif : sinon une
     * promotion temporaire deviendrait un moyen de contourner le réglage.
     */
    public function test_face_auth_follows_the_real_grade_not_the_effective_one(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/parametres/face-auth', ['roles' => [UserRole::Etudiant->value]])
            ->assertOk();

        $etudiantPromu = User::factory()->etudiant()->create();
        PromotionTemporaire::create([
            'etudiant_id' => $etudiantPromu->id,
            'promoteur_id' => User::factory()->enseignant()->create()->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);

        $this->assertSame(UserRole::Delegue, $etudiantPromu->effectiveRole());
        $this->assertTrue($etudiantPromu->requiresFaceAuth());
    }
}
