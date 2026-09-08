<?php

namespace Tests\Feature;

use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Enums\ValidationStatus;
use App\Models\DemandeFormation;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\RequeteEnseignant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pending_items_are_counted_by_kind(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->delegue()->count(2)->create(['validation_status' => ValidationStatus::Pending]);
        User::factory()->enseignant()->count(3)->create(['validation_status' => ValidationStatus::Pending]);
        // Un compte déjà approuvé ne doit pas apparaître comme à traiter.
        User::factory()->delegue()->create(['validation_status' => ValidationStatus::Approved]);

        $enseignant = User::factory()->enseignant()->create();
        RequeteEnseignant::create([
            'seance_id' => Seance::factory()->create()->id,
            'enseignant_id' => $enseignant->id,
            'matiere' => 'Maths', 'salle' => 'A23', 'niveau' => 'L3',
            'description' => 'Contestation', 'statut' => RequestStatus::EnAttente,
            'date_creation' => now(), 'penalite' => 0,
        ]);

        DemandeFormation::create([
            'etudiant_id' => User::factory()->etudiant()->create()->id,
            'statut' => RequestStatus::EnAttente,
            'date_creation' => now(),
        ]);

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk();

        $this->assertSame(2, $reponse->json('a_traiter.delegues'));
        $this->assertSame(3, $reponse->json('a_traiter.enseignants'));
        $this->assertSame(1, $reponse->json('a_traiter.requetes'));
        $this->assertSame(1, $reponse->json('a_traiter.migrations'));
    }

    public function test_attendance_rate_covers_only_the_observed_window(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create();

        // Deux séances dans la fenêtre, dont une seule marquée présente.
        $this->seanceAvecEtat($salle, '2026-09-05', PresenceState::Present);
        $this->seanceAvecEtat($salle, '2026-09-06', PresenceState::Absent);
        // Hors fenêtre : ne doit pas peser dans le taux.
        $this->seanceAvecEtat($salle, '2026-07-01', PresenceState::Absent);

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk();

        $this->assertSame(2, $reponse->json('activite.seances_recentes'));
        $this->assertSame(1, $reponse->json('activite.seances_presentes'));
        $this->assertSame(50, $reponse->json('activite.taux_presence'));
    }

    /**
     * Un taux de 0 % laisserait croire à un problème d'assiduité alors qu'il
     * n'y a simplement rien à mesurer.
     */
    public function test_attendance_rate_is_null_when_there_is_nothing_to_measure(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk();

        $this->assertNull($reponse->json('activite.taux_presence'));
        $this->assertSame(0, $reponse->json('activite.seances_recentes'));
    }

    public function test_today_sessions_are_counted(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create();

        $this->seanceAvecEtat($salle, '2026-09-07', PresenceState::Present);
        $this->seanceAvecEtat($salle, '2026-09-07', PresenceState::Absent);
        $this->seanceAvecEtat($salle, '2026-09-06', PresenceState::Present);

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk();

        $this->assertSame(2, $reponse->json('activite.seances_aujourdhui'));
    }

    public function test_catalogue_counts_are_returned(): void
    {
        $admin = User::factory()->admin()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        Salle::factory()->count(2)->create(['filiere_id' => $filiere->id]);

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk();

        $this->assertSame(1, $reponse->json('catalogue.niveaux'));
        $this->assertSame(1, $reponse->json('catalogue.filieres'));
        $this->assertSame(2, $reponse->json('catalogue.salles'));
    }

    /**
     * Garde anti-régression : l'écran chargeait auparavant les collections
     * entières pour n'en afficher que la longueur. Le nombre de requêtes ne
     * doit pas croître avec le volume de données.
     */
    public function test_query_count_does_not_grow_with_data_volume(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->delegue()->count(2)->create(['validation_status' => ValidationStatus::Pending]);
        $avec2 = $this->compterRequetes(
            fn () => $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk()
        );

        User::factory()->delegue()->count(20)->create(['validation_status' => ValidationStatus::Pending]);
        $avec22 = $this->compterRequetes(
            fn () => $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertOk()
        );

        $this->assertSame($avec2, $avec22);
    }

    public function test_non_admin_cannot_read_the_dashboard(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')->getJson('/api/dashboard')->assertForbidden();
    }

    private function seanceAvecEtat(Salle $salle, string $date, PresenceState $etat): void
    {
        $seance = Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => $date]);

        // etat_final est une colonne générée : elle vaut "present" seulement
        // si le délégué ET l'enseignant ont marqué "present".
        $marquage = $etat === PresenceState::Present ? PresenceState::Present->value : PresenceState::Absent->value;
        $seance->update(['etat_delegue' => $marquage, 'etat_prof' => $marquage]);
    }

    private function compterRequetes(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    }
}
