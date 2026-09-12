<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Enums\StatutCompte;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class GestionEtudiantsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $this->salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- Liste ---------------------------------------------------------

    public function test_list_returns_students_and_delegates_but_not_teachers(): void
    {
        User::factory()->etudiant($this->salle)->count(2)->create();
        User::factory()->delegue($this->salle)->create();
        User::factory()->enseignant()->create();

        $reponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/etudiants')->assertOk();

        $this->assertSame(3, $reponse->json('meta.total'));
    }

    public function test_list_can_be_filtered_by_salle_status_and_search(): void
    {
        $autreSalle = Salle::factory()->create();
        User::factory()->etudiant($this->salle)->create(['name' => 'Awa Ngono', 'phone' => '24I00001']);
        User::factory()->etudiant($this->salle)->create(['name' => 'Paul Biya', 'statut_compte' => StatutCompte::Restreint]);
        User::factory()->etudiant($autreSalle)->create(['name' => 'Awa Fotso']);

        $parSalle = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/etudiants?salle_id={$this->salle->id}")->assertOk();
        $this->assertSame(2, $parSalle->json('meta.total'));

        $parStatut = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/etudiants?statut=restreint')->assertOk();
        $this->assertSame('Paul Biya', $parStatut->json('data.0.name'));

        $parNom = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/etudiants?search=Awa')->assertOk();
        $this->assertSame(2, $parNom->json('meta.total'));

        $parMatricule = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/etudiants?search=24I00001')->assertOk();
        $this->assertSame('Awa Ngono', $parMatricule->json('data.0.name'));
    }

    public function test_non_admin_cannot_manage_students(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')->getJson('/api/etudiants')->assertForbidden();
        $this->actingAs($enseignant, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/restreindre", ['motif' => 'x'])
            ->assertForbidden();
    }

    // --- Changement de salle -------------------------------------------

    public function test_changing_salle_also_moves_filiere_niveau_and_formation(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $autreNiveau = Niveau::factory()->create();
        $autreFiliere = Filiere::factory()->create(['niveau_id' => $autreNiveau->id]);
        $autreSalle = Salle::factory()->create(['filiere_id' => $autreFiliere->id, 'formation' => FormationType::FA]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/etudiants/{$etudiant->id}/salle", ['salle_id' => $autreSalle->id])
            ->assertOk();

        $etudiant->refresh();
        $this->assertSame($autreSalle->id, $etudiant->salle_id);
        $this->assertSame($autreFiliere->id, $etudiant->filiere_id);
        $this->assertSame($autreNiveau->id, $etudiant->niveau_id);
        $this->assertSame(FormationType::FA, $etudiant->formation);
    }

    public function test_moving_to_the_same_salle_is_refused(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/etudiants/{$etudiant->id}/salle", ['salle_id' => $this->salle->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('salle_id');
    }

    public function test_a_teacher_account_cannot_be_managed_as_a_student(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$enseignant->id}/restreindre", ['motif' => 'x'])
            ->assertStatus(422);
    }

    // --- Restriction ----------------------------------------------------

    public function test_restricting_records_reason_and_notifies_the_student(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/restreindre", ['motif' => 'Absences répétées non justifiées'])
            ->assertOk()
            ->assertJsonPath('statut_compte', 'restreint')
            ->assertJsonPath('motif_statut', 'Absences répétées non justifiées');

        $notification = $etudiant->fresh()->notifications()->first();
        $this->assertNotNull($notification, 'Une notification doit être créée.');
        $this->assertSame('restreint', $notification->data['statut']);
        $this->assertSame('Absences répétées non justifiées', $notification->data['motif']);
    }

    public function test_a_reason_is_required_to_restrict_or_block(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/restreindre", [])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/bloquer", [])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');
    }

    public function test_a_restricted_student_can_still_log_in_but_cannot_check_in(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create([
            'password' => bcrypt('secret123'),
            'statut_compte' => StatutCompte::Restreint,
            'motif_statut' => 'Sanction disciplinaire',
        ]);

        $this->postJson('/api/auth/login', ['phone' => $etudiant->phone, 'password' => 'secret123'])
            ->assertOk();

        $seance = $this->seanceActiveAvecPosition();

        $reponse = $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", ['latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('compte');

        $this->assertStringContainsString('Sanction disciplinaire', $reponse->json('errors.compte.0'));
        $this->assertDatabaseMissing('presences_etudiants', ['etudiant_id' => $etudiant->id]);
    }

    // --- Blocage --------------------------------------------------------

    public function test_a_blocked_student_cannot_log_in_and_sees_the_reason(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create(['password' => bcrypt('secret123')]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/bloquer", ['motif' => 'Usurpation de compte'])
            ->assertOk()
            ->assertJsonPath('statut_compte', 'bloque');

        $reponse = $this->postJson('/api/auth/login', ['phone' => $etudiant->phone, 'password' => 'secret123'])
            ->assertUnprocessable();

        $this->assertStringContainsString('bloqué', $reponse->json('errors.phone.0'));
        $this->assertStringContainsString('Usurpation de compte', $reponse->json('errors.phone.0'));
    }

    /**
     * Refuser la connexion ne suffit pas : quelqu'un déjà connecté garderait
     * son accès jusqu'à expiration. Les jetons existants sont révoqués.
     */
    public function test_blocking_revokes_existing_sessions(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();
        $jeton = $etudiant->createToken('presence-app')->plainTextToken;

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/bloquer", ['motif' => 'x'])
            ->assertOk();

        Auth::forgetGuards();
        $this->withToken($jeton)->getJson('/api/auth/me')->assertUnauthorized();
    }

    // --- Rétablissement -------------------------------------------------

    public function test_restoring_reopens_check_in_and_notifies(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create([
            'statut_compte' => StatutCompte::Restreint,
            'motif_statut' => 'ancien motif',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/retablir")
            ->assertOk()
            ->assertJsonPath('statut_compte', 'actif')
            ->assertJsonPath('motif_statut', null);

        $this->assertSame('actif', $etudiant->fresh()->notifications()->first()->data['statut']);

        // L'instance en mémoire date d'avant le rétablissement ; en production
        // Sanctum recharge l'utilisateur à chaque requête, ici on le fait à la main.
        $seance = $this->seanceActiveAvecPosition();
        $this->actingAs($etudiant->fresh(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", ['latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 10])
            ->assertOk();
    }

    public function test_setting_the_same_status_twice_is_refused(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/retablir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');
    }

    // --- Suppression ----------------------------------------------------

    public function test_deleting_removes_the_account(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/etudiants/{$etudiant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $etudiant->id]);
    }

    // --- Présence forcée ------------------------------------------------

    public function test_admin_can_force_presence_outside_the_window_on_a_locked_session(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();
        $seance = Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'date_seance' => '2026-01-10',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'presences_locked' => true,
        ]);
        Carbon::setTestNow('2026-03-01 12:00:00');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/presences/{$etudiant->id}", ['etat' => 'present'])
            ->assertOk();

        $this->assertDatabaseHas('presences_etudiants', [
            'seance_id' => $seance->id,
            'etudiant_id' => $etudiant->id,
            'etat' => 'present',
            'forcee_par_id' => $this->admin->id,
        ]);
    }

    public function test_forcing_presence_refuses_a_student_from_another_salle(): void
    {
        $autreSalle = Salle::factory()->create();
        $etudiant = User::factory()->etudiant($autreSalle)->create();
        $seance = Seance::factory()->create(['salle_id' => $this->salle->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/presences/{$etudiant->id}", ['etat' => 'present'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etudiant');
    }

    public function test_only_admin_can_force_presence(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();
        $delegue = User::factory()->delegue($this->salle)->create();
        $seance = Seance::factory()->create(['salle_id' => $this->salle->id]);

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/presences/{$etudiant->id}", ['etat' => 'present'])
            ->assertForbidden();
    }

    // --- Notifications côté app -----------------------------------------

    public function test_student_can_read_and_acknowledge_notifications(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/restreindre", ['motif' => 'Retards']);

        $liste = $this->actingAs($etudiant, 'sanctum')->getJson('/api/me/notifications')->assertOk();
        $this->assertSame(1, $liste->json('non_lues'));
        $this->assertSame('Retards', $liste->json('notifications.0.motif'));
        $this->assertFalse($liste->json('notifications.0.lue'));

        $id = $liste->json('notifications.0.id');
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/me/notifications/{$id}/lue")
            ->assertOk()
            ->assertJsonPath('non_lues', 0);
    }

    public function test_a_student_cannot_acknowledge_someone_elses_notification(): void
    {
        $cible = User::factory()->etudiant($this->salle)->create();
        $autre = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/etudiants/{$cible->id}/restreindre", ['motif' => 'x']);
        $id = $cible->fresh()->notifications()->first()->id;

        $this->actingAs($autre, 'sanctum')
            ->postJson("/api/me/notifications/{$id}/lue")
            ->assertNotFound();
    }

    // --- Outils ---------------------------------------------------------

    private function seanceActiveAvecPosition(): Seance
    {
        Carbon::setTestNow('2026-02-04 09:00:00');

        $seance = Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'date_seance' => '2026-02-04',
            'jour' => 'MERCREDI',
            'heure_debut' => '08:30',
            'heure_fin' => '10:00',
        ]);

        $delegue = User::factory()->delegue($this->salle)->create();
        $seance->position()->create([
            'delegue_id' => $delegue->id,
            'latitude' => 4.05,
            'longitude' => 9.7,
            'precision_metres' => 10,
        ]);

        return $seance;
    }
}
