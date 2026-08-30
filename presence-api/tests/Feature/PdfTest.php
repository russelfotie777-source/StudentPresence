<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    private function seanceEtSalle(): array
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);
        $seance = Seance::factory()->create(['salle_id' => $salle->id]);

        return [$seance, $salle, $niveau];
    }

    public function test_pdf_requires_locked_seance(): void
    {
        [$seance, $salle, $niveau] = $this->seanceEtSalle();
        $delegue = User::factory()->delegue($salle)->create(['niveau_id' => $niveau->id]);

        $this->actingAs($delegue, 'sanctum')
            ->getJson("/api/seances/{$seance->id}/presence-list.pdf")
            ->assertStatus(422);
    }

    public function test_delegue_can_download_pdf_of_own_locked_seance(): void
    {
        [$seance, $salle, $niveau] = $this->seanceEtSalle();
        $delegue = User::factory()->delegue($salle)->create(['niveau_id' => $niveau->id]);
        $etudiant = User::factory()->etudiant($salle)->create(['niveau_id' => $niveau->id]);
        $seance->update(['presences_locked' => true]);
        $seance->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);

        $response = $this->actingAs($delegue, 'sanctum')
            ->get("/api/seances/{$seance->id}/presence-list.pdf");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_delegue_from_another_room_cannot_download(): void
    {
        [$seance] = $this->seanceEtSalle();
        $seance->update(['presences_locked' => true]);

        $autreSalle = Salle::factory()->create(['filiere_id' => Filiere::factory()->create()]);
        $autreDelegue = User::factory()->delegue($autreSalle)->create();

        $this->actingAs($autreDelegue, 'sanctum')
            ->getJson("/api/seances/{$seance->id}/presence-list.pdf")
            ->assertForbidden();
    }
}
