<?php

namespace Tests\Feature;

use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\RequeteEnseignant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequeteTest extends TestCase
{
    use RefreshDatabase;

    private function seanceFor(User $enseignant): Seance
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);

        return Seance::factory()->create([
            'salle_id' => $salle->id,
            'enseignant_id' => $enseignant->id,
            'etat_delegue' => 'absent',
            'etat_prof' => null,
        ]);
    }

    public function test_teacher_can_submit_a_dispute_with_proof(): void
    {
        Storage::fake('public');

        $enseignant = User::factory()->enseignant()->create();
        $seance = $this->seanceFor($enseignant);

        $response = $this->actingAs($enseignant, 'sanctum')->post('/api/requetes', [
            'seance_id' => $seance->id,
            'description' => "J'étais présent, le délégué a fait une erreur.",
            'preuve' => UploadedFile::fake()->image('preuve.jpg'),
        ]);

        $response->assertCreated()->assertJsonPath('statut', 'en_attente');

        $this->assertDatabaseHas('requetes_enseignants', [
            'seance_id' => $seance->id,
            'enseignant_id' => $enseignant->id,
            'statut' => 'en_attente',
        ]);

        Storage::disk('public')->assertExists(
            RequeteEnseignant::first()->preuve_path
        );
    }

    public function test_teacher_cannot_dispute_another_teachers_session(): void
    {
        Storage::fake('public');

        $enseignant = User::factory()->enseignant()->create();
        $autreEnseignant = User::factory()->enseignant()->create();
        $seance = $this->seanceFor($autreEnseignant);

        $this->actingAs($enseignant, 'sanctum')->post('/api/requetes', [
            'seance_id' => $seance->id,
            'description' => 'Tentative sur une séance qui ne m’appartient pas.',
            'preuve' => UploadedFile::fake()->image('preuve.jpg'),
        ])->assertForbidden();
    }

    public function test_admin_approval_forces_both_etat_prof_and_etat_delegue(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $enseignant = User::factory()->enseignant()->create();
        $seance = $this->seanceFor($enseignant); // etat_delegue=absent, etat_prof=null au départ

        $requete = RequeteEnseignant::create([
            'seance_id' => $seance->id,
            'enseignant_id' => $enseignant->id,
            'matiere' => 'Test',
            'salle' => $seance->salle->nom,
            'niveau' => 'L1',
            'penalite' => 0,
            'description' => 'Erreur de marquage.',
            'statut' => RequestStatus::EnAttente,
            'date_creation' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/requetes/{$requete->id}/process", ['action' => 'acceptee']);

        $response->assertOk()->assertJsonPath('statut', 'acceptee');

        $seance->refresh();
        $this->assertEquals(PresenceState::Present, $seance->etat_delegue);
        $this->assertEquals(PresenceState::Present, $seance->etat_prof);
        $this->assertEquals(PresenceState::Present, $seance->etat_final);
    }

    public function test_rejected_dispute_does_not_change_the_seance(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $enseignant = User::factory()->enseignant()->create();
        $seance = $this->seanceFor($enseignant);

        $requete = RequeteEnseignant::create([
            'seance_id' => $seance->id,
            'enseignant_id' => $enseignant->id,
            'matiere' => 'Test',
            'salle' => $seance->salle->nom,
            'niveau' => 'L1',
            'penalite' => 0,
            'description' => 'Erreur de marquage.',
            'statut' => RequestStatus::EnAttente,
            'date_creation' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/requetes/{$requete->id}/process", ['action' => 'rejetee'])
            ->assertOk()
            ->assertJsonPath('statut', 'rejetee');

        $seance->refresh();
        $this->assertEquals('absent', $seance->etat_delegue->value);
        $this->assertNull($seance->etat_prof);
    }

    public function test_cannot_process_an_already_processed_dispute(): void
    {
        $admin = User::factory()->admin()->create();
        $enseignant = User::factory()->enseignant()->create();
        $seance = $this->seanceFor($enseignant);

        $requete = RequeteEnseignant::create([
            'seance_id' => $seance->id,
            'enseignant_id' => $enseignant->id,
            'matiere' => 'Test',
            'salle' => $seance->salle->nom,
            'niveau' => 'L1',
            'penalite' => 0,
            'description' => 'Déjà traitée.',
            'statut' => RequestStatus::Acceptee,
            'date_creation' => now(),
            'date_traitement' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/requetes/{$requete->id}/process", ['action' => 'rejetee'])
            ->assertUnprocessable();
    }
}
