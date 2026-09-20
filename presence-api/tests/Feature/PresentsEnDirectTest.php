<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\PresenceEtudiant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Qui est là » pendant la séance : le compte de présents sur l'effectif
 * et les prénoms des derniers arrivés, pour la classe, l'enseignant de la
 * séance et l'admin — pas pour une autre salle.
 */
class PresentsEnDirectTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    private Seance $seance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-02-04 09:00:00');
        $this->salle = Salle::factory()->create(['formation' => FormationType::FI]);
        Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);
        $this->seance = Seance::factory()->create([
            'salle_id' => $this->salle->id, 'semaine_id' => Semaine::first()->id,
            'date_seance' => '2026-02-04', 'jour' => 'MERCREDI', 'heure_debut' => '08:30', 'heure_fin' => '10:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_class_sees_how_many_are_here_and_the_last_first_names(): void
    {
        $moi = User::factory()->etudiant($this->salle)->create(['name' => 'Awa Ndiaye']);
        $camarades = collect(['MBALLA Étienne', 'Paul Essomba', 'Marie NKOLO', 'Jean Kamga', 'Rose Fotso'])
            ->map(fn ($nom) => User::factory()->etudiant($this->salle)->create(['name' => $nom]));
        User::factory()->etudiant($this->salle)->create(['name' => 'Absent Toujours']);

        foreach ($camarades->take(4) as $i => $c) {
            Carbon::setTestNow('2026-02-04 08:4'.$i.':00');
            PresenceEtudiant::create(['seance_id' => $this->seance->id, 'etudiant_id' => $c->id, 'etat' => 'present', 'date_marquage' => now()]);
        }
        Carbon::setTestNow('2026-02-04 08:50:00');
        PresenceEtudiant::create(['seance_id' => $this->seance->id, 'etudiant_id' => $camarades[4]->id, 'etat' => 'absent', 'date_marquage' => now()]);

        $this->actingAs($moi, 'sanctum')->getJson("/api/seances/{$this->seance->id}/presents")
            ->assertOk()
            ->assertJsonPath('presents', 4)
            ->assertJsonPath('effectif', 7)
            ->assertJsonPath('moi', false)
            ->assertJsonPath('prenoms', ['Jean', 'Marie', 'Paul', 'Étienne'], 'Les derniers arrivés d\'abord, par leur prénom, même quand le nom est en capitales devant.');

        Carbon::setTestNow('2026-02-04 09:01:00');
        PresenceEtudiant::create(['seance_id' => $this->seance->id, 'etudiant_id' => $moi->id, 'etat' => 'present', 'date_marquage' => now()]);
        $this->actingAs($moi, 'sanctum')->getJson("/api/seances/{$this->seance->id}/presents")
            ->assertJsonPath('presents', 5)
            ->assertJsonPath('moi', true)
            ->assertJsonPath('prenoms', ['Jean', 'Marie', 'Paul', 'Étienne'], 'Celui qui regarde ne figure pas dans la liste : il sait qu\'il est là.');
    }

    public function test_the_teacher_of_the_seance_and_the_admin_see_it_but_another_class_does_not(): void
    {
        $this->actingAs($this->seance->enseignant, 'sanctum')->getJson("/api/seances/{$this->seance->id}/presents")->assertOk();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')->getJson("/api/seances/{$this->seance->id}/presents")->assertOk();
        $this->actingAs(User::factory()->delegue($this->salle)->create(), 'sanctum')->getJson("/api/seances/{$this->seance->id}/presents")->assertOk();

        $this->actingAs(User::factory()->etudiant(Salle::factory()->create())->create(), 'sanctum')
            ->getJson("/api/seances/{$this->seance->id}/presents")->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create(), 'sanctum')
            ->getJson("/api/seances/{$this->seance->id}/presents")->assertForbidden();
    }
}
