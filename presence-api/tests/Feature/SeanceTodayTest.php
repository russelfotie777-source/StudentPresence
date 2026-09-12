<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une séance programmée pour une salle doit apparaître chez les étudiants
 * de cette salle — et seulement chez eux — le jour dit, à l'heure de Douala.
 */
class SeanceTodayTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    private Semaine $semaine;

    protected function setUp(): void
    {
        parent::setUp();

        // Lundi 14 septembre 2026, 07:30 à Douala.
        Carbon::setTestNow(Carbon::parse('2026-09-14 07:30:00', 'Africa/Douala'));

        $filiere = Filiere::factory()->create();
        $this->salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
        $this->semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-20']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(array $attributs = []): Seance
    {
        return Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'semaine_id' => $this->semaine->id,
            'date_seance' => '2026-09-14',
            'jour' => 'LUNDI',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            ...$attributs,
        ]);
    }

    public function test_students_of_the_room_see_the_session_scheduled_today(): void
    {
        $seance = $this->seance();
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today');

        $response->assertOk();
        $this->assertSame([$seance->id], $response->json('*.id'));
        $this->assertSame($this->salle->nom, $response->json('0.salle'));
    }

    public function test_delegate_of_the_room_sees_it_too(): void
    {
        $seance = $this->seance();
        $delegue = User::factory()->delegue($this->salle)->create();

        $response = $this->actingAs($delegue, 'sanctum')->getJson('/api/seances/today');

        $this->assertSame([$seance->id], $response->json('*.id'));
    }

    /**
     * Les étudiants FM sont rattachés à la salle FI : même salle_id, donc
     * même emploi du temps, sans traitement particulier.
     */
    public function test_fm_student_attached_to_the_fi_room_sees_it(): void
    {
        $seance = $this->seance();
        $fm = User::factory()->etudiant($this->salle)->create(['formation' => FormationType::FM]);

        $response = $this->actingAs($fm, 'sanctum')->getJson('/api/seances/today');

        $this->assertSame([$seance->id], $response->json('*.id'));
    }

    public function test_students_of_another_room_do_not_see_it(): void
    {
        $this->seance();
        $autreSalle = Salle::factory()->create();
        $etudiant = User::factory()->etudiant($autreSalle)->create();

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today');

        $response->assertOk()->assertJsonCount(0);
    }

    /**
     * Même jour de semaine, autre semaine : ce n'est pas aujourd'hui. L'ancien
     * filtre (jour + semaine « la plus proche ») affichait ces séances-là
     * dès qu'aucune semaine ne couvrait la date du jour.
     */
    public function test_same_weekday_in_another_week_is_not_today(): void
    {
        $semaine2 = Semaine::factory()->create(['numero' => 2, 'date_debut' => '2026-09-21', 'date_fin' => '2026-09-27']);
        $this->seance(['semaine_id' => $semaine2->id, 'date_seance' => '2026-09-21']);
        $etudiant = User::factory()->etudiant($this->salle)->create();

        // Aujourd'hui = 14 septembre, couvert par la semaine 1 ; la séance est le 21.
        $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today')->assertJsonCount(0);

        // Hors de toute semaine (vacances), rien ne doit apparaître non plus.
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:30:00', 'Africa/Douala'));
        $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today')->assertJsonCount(0);
    }

    /**
     * Le jour est celui de Douala : à 23:30 UTC le lundi, il est déjà mardi
     * 00:30 à Douala — la séance du lundi n'est plus « aujourd'hui ».
     */
    public function test_today_follows_douala_not_utc(): void
    {
        $this->seance();
        $etudiant = User::factory()->etudiant($this->salle)->create();

        Carbon::setTestNow(Carbon::parse('2026-09-14 23:30:00', 'UTC'));
        $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today')->assertJsonCount(0);

        Carbon::setTestNow(Carbon::parse('2026-09-13 23:30:00', 'UTC')); // 00:30 lundi à Douala
        $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today')->assertJsonCount(1);
    }

    /**
     * Séance saisie à la main sans date (héritage) : repérée par son jour dans
     * la semaine qui couvre aujourd'hui.
     */
    public function test_legacy_session_without_date_falls_back_to_weekday_of_the_current_week(): void
    {
        $seance = $this->seance(['date_seance' => null]);
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/today');

        $this->assertSame([$seance->id], $response->json('*.id'));
    }
}
