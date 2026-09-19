<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les matières appartiennent à une filière (donc à un niveau et à un
 * département) ; sans filière, elles sont communes à toutes.
 */
class MatiereTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_subject_belongs_to_a_filiere_and_codes_are_unique_within_it(): void
    {
        $admin = User::factory()->admin()->create();
        [$gi, $grt] = Filiere::factory()->count(2)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/matieres', ['nom' => 'Algorithmique', 'code' => 'INF101', 'filiere_id' => $gi->id])
            ->assertCreated()
            ->assertJsonPath('filiere.id', $gi->id);

        // Le même code dans une autre filière : permis. Deux fois dans la même : refusé.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/matieres', ['nom' => 'Algorithmique', 'code' => 'INF101', 'filiere_id' => $grt->id])
            ->assertCreated();
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/matieres', ['nom' => 'Algo bis', 'code' => 'INF101', 'filiere_id' => $gi->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        // Sans filière : commune, et son code est unique parmi les communes.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/matieres', ['nom' => 'Anglais', 'code' => 'ANG101'])
            ->assertCreated()
            ->assertJsonPath('filiere', null);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/matieres', ['nom' => 'Anglais 2', 'code' => 'ANG101'])
            ->assertUnprocessable();
    }

    public function test_a_course_cannot_use_a_subject_of_another_filiere(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create();
        $ailleurs = Matiere::factory()->create(['filiere_id' => Filiere::factory()->create()->id]);
        $commune = Matiere::factory()->create(['filiere_id' => null]);
        $prof = User::factory()->enseignant()->create();
        $cours = ['enseignant_id' => $prof->id, 'salle_id' => $salle->id, 'jour' => 'LUNDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-14'];

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/course-templates', ['matiere_id' => $ailleurs->id] + $cours)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('matiere_id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/course-templates', ['matiere_id' => $commune->id] + $cours)
            ->assertCreated();
    }

    public function test_listing_by_filiere_returns_its_subjects_and_the_common_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $departement = Departement::factory()->create();
        $gi = Filiere::factory()->create(['departement_id' => $departement->id]);
        $grt = Filiere::factory()->create();
        Matiere::factory()->create(['nom' => 'Algo GI', 'filiere_id' => $gi->id]);
        Matiere::factory()->create(['nom' => 'Réseaux GRT', 'filiere_id' => $grt->id]);
        Matiere::factory()->create(['nom' => 'Anglais', 'filiere_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/matieres?filiere_id={$gi->id}")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.nom', 'Algo GI')
            ->assertJsonPath('1.nom', 'Anglais');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/matieres?departement_id={$departement->id}")
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/matieres')
            ->assertOk()
            ->assertJsonCount(3);
    }
}
