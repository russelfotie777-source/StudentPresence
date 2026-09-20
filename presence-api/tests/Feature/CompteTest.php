<?php

namespace Tests\Feature;

use App\Mail\CodeParEmail;
use App\Models\CodeEmail;
use App\Models\Salle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Les identifiants qu'un utilisateur tient lui-même à jour : mot de passe
 * (obligatoirement remplacé quand c'est l'initial commun), numéro de
 * l'enseignant, adresse e-mail confirmée par code — et le mot de passe
 * oublié qui en découle. Le matricule ne bouge jamais.
 */
class CompteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-09-20 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- mot de passe ---------------------------------------------------------

    public function test_the_initial_password_locks_business_routes_until_replaced(): void
    {
        $etudiant = User::factory()->etudiant(Salle::factory()->create())->create([
            'password' => '12345678', 'doit_changer_mot_de_passe' => true,
        ]);

        $this->postJson('/api/auth/login', ['phone' => $etudiant->phone, 'password' => '12345678'])
            ->assertOk()
            ->assertJsonPath('user.doit_changer_mot_de_passe', true);

        $this->actingAs($etudiant, 'sanctum')->getJson('/api/me/notifications')
            ->assertForbidden()
            ->assertJsonPath('message', 'Remplacez d\'abord le mot de passe initial de votre compte.');

        $this->actingAs($etudiant, 'sanctum')->putJson('/api/auth/compte/mot-de-passe', [
            'mot_de_passe_actuel' => '12345678',
            'mot_de_passe' => 'nouveau-secret', 'mot_de_passe_confirmation' => 'nouveau-secret',
        ])->assertOk()->assertJsonPath('user.doit_changer_mot_de_passe', false);

        $this->assertTrue(Hash::check('nouveau-secret', $etudiant->fresh()->password));
        $this->actingAs($etudiant->fresh(), 'sanctum')->getJson('/api/me/notifications')->assertOk();
    }

    public function test_changing_the_password_follows_the_rules_and_logs_out_other_devices(): void
    {
        $user = User::factory()->enseignant()->create(['password' => 'ancien-secret']);
        $autreAppareil = $user->createToken('autre')->plainTextToken;
        $ici = $user->createToken('ici')->plainTextToken;

        $changer = fn (array $corps) => $this->avecJeton($ici)->putJson('/api/auth/compte/mot-de-passe', $corps + ['mot_de_passe_confirmation' => $corps['mot_de_passe'] ?? '']);

        $changer(['mot_de_passe_actuel' => 'faux', 'mot_de_passe' => 'nouveau-secret'])
            ->assertUnprocessable()->assertJsonPath('errors.mot_de_passe_actuel.0', 'Le mot de passe actuel est incorrect.');
        $changer(['mot_de_passe_actuel' => 'ancien-secret', 'mot_de_passe' => '12345678'])
            ->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe' => 'remis à la création']);
        $changer(['mot_de_passe_actuel' => 'ancien-secret', 'mot_de_passe' => 'ancien-secret'])
            ->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe' => 'différent']);
        $changer(['mot_de_passe_actuel' => 'ancien-secret', 'mot_de_passe' => 'court'])
            ->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);
        $this->avecJeton($ici)->putJson('/api/auth/compte/mot-de-passe', [
            'mot_de_passe_actuel' => 'ancien-secret', 'mot_de_passe' => 'nouveau-secret', 'mot_de_passe_confirmation' => 'autre',
        ])->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);

        $changer(['mot_de_passe_actuel' => 'ancien-secret', 'mot_de_passe' => 'nouveau-secret'])->assertOk();

        $this->avecJeton($autreAppareil)->getJson('/api/auth/me')->assertUnauthorized();
        $this->avecJeton($ici)->getJson('/api/auth/me')->assertOk();
    }

    // --- téléphone ------------------------------------------------------------

    public function test_only_a_teacher_changes_their_phone_which_is_their_login(): void
    {
        $prof = User::factory()->enseignant()->create(['phone' => 'ENS0001', 'password' => 'secret-prof']);
        User::factory()->enseignant()->create(['phone' => '699000010']);

        $this->actingAs($prof, 'sanctum')->putJson('/api/auth/compte/telephone', ['telephone' => '699000010', 'mot_de_passe_actuel' => 'secret-prof'])
            ->assertUnprocessable()->assertJsonPath('errors.telephone.0', 'Ce numéro est déjà utilisé par un autre compte.');

        $this->actingAs($prof, 'sanctum')->putJson('/api/auth/compte/telephone', ['telephone' => '677123456', 'mot_de_passe_actuel' => 'secret-prof'])
            ->assertOk()->assertJsonPath('user.phone', '677123456');

        $this->postJson('/api/auth/login', ['phone' => '677123456', 'password' => 'secret-prof'])->assertOk();

        $etudiant = User::factory()->etudiant(Salle::factory()->create())->create(['password' => 'secret']);
        $this->actingAs($etudiant, 'sanctum')->putJson('/api/auth/compte/telephone', ['telephone' => '655000000', 'mot_de_passe_actuel' => 'secret'])
            ->assertForbidden();
        $this->assertSame($etudiant->phone, $etudiant->fresh()->phone, 'Le matricule ne se touche pas.');
    }

    // --- e-mail ---------------------------------------------------------------

    public function test_an_email_becomes_the_account_address_only_once_its_code_is_confirmed(): void
    {
        $user = User::factory()->etudiant(Salle::factory()->create())->create(['email' => null]);

        $this->actingAs($user, 'sanctum')->putJson('/api/auth/compte/email', ['email' => 'Awa.Ndiaye@Example.com'])
            ->assertOk()
            ->assertJsonPath('email_en_attente', 'awa.ndiaye@example.com')
            ->assertJsonPath('user.email', null);

        $code = $this->codeEnvoye('awa.ndiaye@example.com');
        $this->assertNull($user->fresh()->email, 'L\'adresse n\'est pas encore celle du compte.');
        $this->actingAs($user, 'sanctum')->getJson('/api/auth/me')->assertJsonPath('email_en_attente', 'awa.ndiaye@example.com');

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Encore 4 essais']);

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', 'awa.ndiaye@example.com')
            ->assertJsonPath('user.email_verifie', true)
            ->assertJsonPath('email_en_attente', null);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('codes_email', 0);
    }

    public function test_a_code_dies_after_five_wrong_tries_or_thirty_minutes_and_is_not_resent_within_a_minute(): void
    {
        $user = User::factory()->enseignant()->create(['email' => null]);
        $this->actingAs($user, 'sanctum')->putJson('/api/auth/compte/email', ['email' => 'prof@example.com'])->assertOk();
        $code = $this->codeEnvoye('prof@example.com');

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/renvoyer')
            ->assertUnprocessable()->assertJsonValidationErrors(['code' => 'vient d\'être envoyé']);

        for ($i = 1; $i <= 4; $i++) {
            $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => '999999'])->assertUnprocessable();
        }
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => '999999'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Trop d\'essais']);
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Aucun code en attente']);

        Carbon::setTestNow('2026-09-20 09:02:00');
        $this->actingAs($user, 'sanctum')->putJson('/api/auth/compte/email', ['email' => 'prof@example.com'])->assertOk();
        $code = $this->codeEnvoye('prof@example.com', 2);
        Carbon::setTestNow('2026-09-20 09:33:00');
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors(['code' => 'expiré']);
        $this->assertNull($user->fresh()->email);
    }

    public function test_an_address_already_verified_by_someone_else_is_refused(): void
    {
        User::factory()->enseignant()->create(['email' => 'prise@example.com', 'email_verified_at' => now()]);
        $user = User::factory()->enseignant()->create(['email' => null]);

        $this->actingAs($user, 'sanctum')->putJson('/api/auth/compte/email', ['email' => 'prise@example.com'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Cette adresse est déjà utilisée par un autre compte.');
        Mail::assertNothingSent();
    }

    /** Une adresse saisie par l'admin ou l'assistant reste à confirmer : le code se renvoie sans la retaper. */
    public function test_an_unverified_address_given_at_creation_can_be_confirmed_by_resending_a_code(): void
    {
        $user = User::factory()->enseignant()->create(['email' => 'donnee@example.com', 'email_verified_at' => null]);

        $this->actingAs($user, 'sanctum')->getJson('/api/auth/me')->assertJsonPath('user.email_verifie', false);
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/renvoyer')->assertOk();
        $code = $this->codeEnvoye('donnee@example.com');
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/compte/email/verifier', ['code' => $code])
            ->assertOk()->assertJsonPath('user.email_verifie', true);
    }

    // --- mot de passe oublié --------------------------------------------------

    public function test_a_forgotten_password_is_reset_by_code_sent_to_the_verified_address(): void
    {
        $user = User::factory()->etudiant(Salle::factory()->create())->create([
            'phone' => '24I09001', 'email' => 'awa@example.com', 'email_verified_at' => now(), 'password' => 'perdu',
        ]);
        $ancienJeton = $user->createToken('telephone')->plainTextToken;

        $this->postJson('/api/auth/mot-de-passe-oublie', ['phone' => '24I09001'])
            ->assertOk()->assertJsonPath('email_masque', 'a***@example.com');
        $code = $this->codeEnvoye('awa@example.com');

        $this->postJson('/api/auth/mot-de-passe-oublie/reinitialiser', [
            'phone' => '24I09001', 'code' => '123456', 'mot_de_passe' => 'retrouve-1', 'mot_de_passe_confirmation' => 'retrouve-1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);

        $this->postJson('/api/auth/mot-de-passe-oublie/reinitialiser', [
            'phone' => '24I09001', 'code' => $code, 'mot_de_passe' => 'retrouve-1', 'mot_de_passe_confirmation' => 'retrouve-1',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['phone' => '24I09001', 'password' => 'retrouve-1'])->assertOk();
        $this->avecJeton($ancienJeton)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_without_a_verified_address_the_forgotten_password_points_to_the_administration(): void
    {
        User::factory()->etudiant(Salle::factory()->create())->create(['phone' => '24I09002', 'email' => 'jamais@example.com', 'email_verified_at' => null]);

        foreach (['24I09002', 'inconnu'] as $phone) {
            $this->postJson('/api/auth/mot-de-passe-oublie', ['phone' => $phone])
                ->assertUnprocessable()->assertJsonValidationErrors(['phone' => 'administration']);
        }
        Mail::assertNothingSent();
    }

    // --- réinitialisation par l'admin ---------------------------------------

    public function test_the_admin_resets_a_lost_account_to_the_initial_password(): void
    {
        $admin = User::factory()->admin()->create();
        $prof = User::factory()->enseignant()->create(['password' => 'perdu']);
        $jeton = $prof->createToken('telephone')->plainTextToken;

        $this->actingAs($admin, 'sanctum')->postJson("/api/comptes/{$prof->id}/reinitialiser-mot-de-passe")
            ->assertOk()->assertJsonPath('mot_de_passe_initial', '12345678');

        $prof->refresh();
        $this->assertTrue(Hash::check('12345678', $prof->password));
        $this->assertTrue($prof->doit_changer_mot_de_passe);
        $this->avecJeton($jeton)->getJson('/api/auth/me')->assertUnauthorized();

        $this->actingAs($admin, 'sanctum')->postJson("/api/comptes/{$admin->id}/reinitialiser-mot-de-passe")->assertForbidden();
        $this->actingAs($prof, 'sanctum')->postJson("/api/comptes/{$prof->id}/reinitialiser-mot-de-passe")->assertForbidden();
    }

    /** Comme withToken, mais sans l'utilisateur mis en cache par le garde d'une requête à l'autre. */
    private function avecJeton(string $jeton): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton);
    }

    /** Le code du dernier e-mail envoyé à cette adresse. */
    private function codeEnvoye(string $email, int $attendus = 1): string
    {
        Mail::assertSent(CodeParEmail::class, $attendus);
        $code = null;
        Mail::assertSent(CodeParEmail::class, function (CodeParEmail $mail) use ($email, &$code) {
            if ($mail->hasTo($email)) {
                $code = $mail->code;
            }

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->assertSame(1, CodeEmail::count());

        return $code;
    }
}
