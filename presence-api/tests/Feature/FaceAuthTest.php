<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FaceAuthTest extends TestCase
{
    use RefreshDatabase;

    private function descriptor(float $seed = 0.1): array
    {
        return array_fill(0, 128, $seed);
    }

    /**
     * Sanctum's guard memoizes the resolved user on the underlying
     * RequestGuard instance, which the AuthManager caches per guard name —
     * fine in production (each real HTTP request boots a fresh app), but
     * within one test method reusing the same app across calls, a second
     * request with a different token would otherwise still resolve to the
     * first token's user. Force it to re-resolve whenever a test switches
     * tokens mid-method.
     */
    private function withFreshToken(string $token): static
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    public function test_student_login_requires_face_and_is_not_yet_enrolled(): void
    {
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_face', true)
            ->assertJsonPath('face_enrolled', false);
    }

    public function test_non_student_login_does_not_require_face(): void
    {
        $enseignant = User::factory()->enseignant()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => $enseignant->phone,
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonPath('requires_face', false);

        // Un jeton complet dès la connexion : accès direct aux routes métier.
        $token = $response->json('token');
        $this->withFreshToken($token)->getJson('/api/seances/today')->assertOk();
    }

    public function test_pending_token_cannot_access_business_routes(): void
    {
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('password123')]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $this->withFreshToken($login->json('token'))
            ->getJson('/api/seances/today')
            ->assertForbidden();
    }

    public function test_student_can_enroll_face_on_first_login_and_then_access_business_routes(): void
    {
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('password123')]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);
        $pendingToken = $login->json('token');

        $enroll = $this->withFreshToken($pendingToken)->postJson('/api/auth/face/enroll', [
            'descriptor' => $this->descriptor(),
        ]);

        $enroll->assertOk();
        $fullToken = $enroll->json('token');
        $this->assertNotSame($pendingToken, $fullToken);

        $this->assertDatabaseHas('users', ['id' => $etudiant->id]);
        $this->assertNotNull($etudiant->fresh()->face_descriptor);

        $this->withFreshToken($fullToken)->getJson('/api/seances/today')->assertOk();
        // Le jeton "en attente" a été révoqué au passage.
        $this->withFreshToken($pendingToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_cannot_enroll_twice(): void
    {
        $etudiant = User::factory()->etudiant()->create([
            'password' => bcrypt('password123'),
            'face_descriptor' => $this->descriptor(),
            'face_enrolled_at' => now(),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);
        $login->assertJsonPath('face_enrolled', true);

        $this->withFreshToken($login->json('token'))
            ->postJson('/api/auth/face/enroll', ['descriptor' => $this->descriptor()])
            ->assertStatus(422);
    }

    public function test_student_can_verify_matching_face_on_subsequent_login(): void
    {
        $etudiant = User::factory()->etudiant()->create([
            'password' => bcrypt('password123'),
            'face_descriptor' => $this->descriptor(0.42),
            'face_enrolled_at' => now(),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $verify = $this->withFreshToken($login->json('token'))->postJson('/api/auth/face/verify', [
            'descriptor' => $this->descriptor(0.42),
        ]);

        $verify->assertOk();
        $this->withFreshToken($verify->json('token'))->getJson('/api/seances/today')->assertOk();
    }

    public function test_verify_rejects_a_different_face(): void
    {
        $etudiant = User::factory()->etudiant()->create([
            'password' => bcrypt('password123'),
            'face_descriptor' => $this->descriptor(0.1),
            'face_enrolled_at' => now(),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $this->withFreshToken($login->json('token'))
            ->postJson('/api/auth/face/verify', ['descriptor' => $this->descriptor(0.9)])
            ->assertStatus(422);
    }

    public function test_verify_rejects_a_similar_but_distinct_face_under_the_tightened_threshold(): void
    {
        // Distance ~0.48 entre les deux descripteurs : aurait été acceptée
        // sous l'ancien seuil (0.55, trop permissif pour des visages très
        // ressemblants — voir le commentaire sur FaceController::MATCH_THRESHOLD,
        // resserré après qu'une sœur ait été acceptée à la place de
        // l'utilisatrice inscrite) mais doit être refusée sous le seuil
        // resserré (0.42).
        $base = array_fill(0, 128, 0.2);
        $delta = 0.48 / sqrt(128);
        $similar = array_map(fn ($value) => $value + $delta, $base);

        $etudiant = User::factory()->etudiant()->create([
            'password' => bcrypt('password123'),
            'face_descriptor' => $base,
            'face_enrolled_at' => now(),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $this->withFreshToken($login->json('token'))
            ->postJson('/api/auth/face/verify', ['descriptor' => $similar])
            ->assertStatus(422);
    }

    public function test_a_full_token_cannot_call_face_endpoints(): void
    {
        $etudiant = User::factory()->etudiant()->create([
            'face_descriptor' => $this->descriptor(),
            'face_enrolled_at' => now(),
        ]);
        $fullToken = $etudiant->createToken('presence-app')->plainTextToken;

        $this->withFreshToken($fullToken)
            ->postJson('/api/auth/face/verify', ['descriptor' => $this->descriptor()])
            ->assertForbidden();
    }

    public function test_me_reports_face_pending_for_a_pending_token(): void
    {
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('password123')]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $this->withFreshToken($login->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('face_pending', true)
            ->assertJsonPath('face_enrolled', false);
    }

    public function test_me_reports_face_pending_false_once_verified(): void
    {
        $etudiant = User::factory()->etudiant()->create([
            'password' => bcrypt('password123'),
            'face_descriptor' => $this->descriptor(0.3),
            'face_enrolled_at' => now(),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $verify = $this->withFreshToken($login->json('token'))->postJson('/api/auth/face/verify', [
            'descriptor' => $this->descriptor(0.3),
        ]);

        $this->withFreshToken($verify->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('face_pending', false)
            ->assertJsonPath('face_enrolled', true);
    }

    public function test_descriptor_must_have_exactly_128_values(): void
    {
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('password123')]);

        $login = $this->postJson('/api/auth/login', [
            'phone' => $etudiant->phone,
            'password' => 'password123',
        ]);

        $this->withFreshToken($login->json('token'))
            ->postJson('/api/auth/face/enroll', ['descriptor' => [0.1, 0.2]])
            ->assertStatus(422);
    }
}
