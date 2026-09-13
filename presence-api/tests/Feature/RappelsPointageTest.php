<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Models\PositionSeance;
use App\Models\PresenceEtudiant;
use App\Models\PromotionTemporaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Notifications\RappelPointage;
use App\Services\RappelsPointage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * Rappels de pointage : au délégué d'envoyer la position, aux étudiants que
 * le pointage est ouvert, puis qu'il ferme. Chacun une seule fois, à l'heure
 * de Douala.
 */
class RappelsPointageTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    private Seance $seance;

    private User $delegue;

    private User $a;

    private User $b;

    private User $restreint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salle = Salle::factory()->create();
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-20']);
        // Lundi 14 septembre 2026, 08:00–10:00 : fenêtre de pointage 07:45 → 10:15.
        $this->seance = Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'semaine_id' => $semaine->id,
            'date_seance' => '2026-09-14',
            'jour' => 'LUNDI',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ]);

        $this->delegue = User::factory()->delegue($this->salle)->create();
        $this->a = User::factory()->etudiant($this->salle)->create(['name' => 'A']);
        $this->b = User::factory()->etudiant($this->salle)->create(['name' => 'B']);
        $this->restreint = User::factory()->etudiant($this->salle)->create(['statut_compte' => StatutCompte::Restreint]);
        User::factory()->etudiant(Salle::factory()->create())->create(['name' => 'Autre salle']);

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function a(string $heure): Carbon
    {
        $instant = Carbon::parse("2026-09-14 {$heure}", 'Africa/Douala');
        Carbon::setTestNow($instant);

        return $instant;
    }

    private function position(): void
    {
        PositionSeance::create([
            'seance_id' => $this->seance->id,
            'delegue_id' => $this->delegue->id,
            'latitude' => 4.05,
            'longitude' => 9.7,
            'precision_metres' => 10,
            'date_creation' => now(),
        ]);
    }

    private function sousType(string $attendu): callable
    {
        return fn (RappelPointage $n) => $n->sousType === $attendu && $n->seance->is($this->seance);
    }

    // --- délégué ------------------------------------------------------------

    public function test_delegate_is_reminded_once_when_the_window_opens_without_a_position(): void
    {
        $rappels = app(RappelsPointage::class);

        $rappels->tick($this->a('07:44'));
        Notification::assertNothingSent();

        $rappels->tick($this->a('07:45'));
        Notification::assertSentTo($this->delegue, RappelPointage::class, $this->sousType(RappelPointage::DELEGUE));
        Notification::assertNotSentTo([$this->a, $this->b, $this->restreint], RappelPointage::class);

        $rappels->tick($this->a('07:46'));
        Notification::assertSentToTimes($this->delegue, RappelPointage::class, 1);
    }

    public function test_delegate_is_not_reminded_when_the_position_is_already_there(): void
    {
        $this->position();

        app(RappelsPointage::class)->rappelerDelegues($this->a('07:50'));

        Notification::assertNotSentTo($this->delegue, RappelPointage::class);
    }

    public function test_a_temporarily_promoted_student_is_reminded_like_the_delegate(): void
    {
        PromotionTemporaire::create([
            'etudiant_id' => $this->a->id,
            'promoteur_id' => User::factory()->enseignant()->create()->id,
            'date_debut' => $this->a('07:00'),
            'date_fin' => Carbon::parse('2026-09-14 12:00', 'Africa/Douala'),
            'duree_minutes' => 300,
        ]);

        app(RappelsPointage::class)->rappelerDelegues($this->a('07:50'));

        Notification::assertSentTo([$this->delegue, $this->a], RappelPointage::class, $this->sousType(RappelPointage::DELEGUE));
        Notification::assertNotSentTo($this->b, RappelPointage::class);
    }

    // --- ouverture ----------------------------------------------------------

    public function test_students_who_have_not_checked_in_are_told_when_the_position_arrives(): void
    {
        $this->a('07:50');
        PresenceEtudiant::create(['seance_id' => $this->seance->id, 'etudiant_id' => $this->a->id, 'etat' => 'present']);

        $envoi = fn () => $this->actingAs($this->delegue, 'sanctum')
            ->postJson("/api/seances/{$this->seance->id}/position", ['latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 12]);

        $envoi()->assertCreated();

        Notification::assertSentTo($this->b, RappelPointage::class, $this->sousType(RappelPointage::OUVERTURE));
        Notification::assertNotSentTo([$this->a, $this->restreint, $this->delegue], RappelPointage::class);
        $this->assertNotNull($this->seance->fresh()->rappel_ouverture_at);

        // Le délégué renvoie sa position : pas de second rappel.
        $envoi()->assertCreated();
        Notification::assertSentToTimes($this->b, RappelPointage::class, 1);
    }

    public function test_the_tick_catches_up_an_opening_that_was_not_announced(): void
    {
        $this->a('07:50');
        $this->position();
        $rappels = app(RappelsPointage::class);

        $rappels->tick(now());
        Notification::assertSentTo([$this->a, $this->b], RappelPointage::class, $this->sousType(RappelPointage::OUVERTURE));

        $rappels->tick($this->a('07:51'));
        Notification::assertSentToTimes($this->a, RappelPointage::class, 1);
    }

    public function test_a_position_sent_before_the_window_does_not_announce_yet(): void
    {
        $this->a('07:00');
        $this->position();

        app(RappelsPointage::class)->annoncerOuverture($this->seance->fresh(), now());

        Notification::assertNothingSent();
        $this->assertNull($this->seance->fresh()->rappel_ouverture_at, 'Le passage planifié l\'annoncera à 07:45.');
    }

    // --- dernière chance ----------------------------------------------------

    public function test_last_call_goes_only_to_those_still_missing_and_only_once(): void
    {
        $this->position();
        PresenceEtudiant::create(['seance_id' => $this->seance->id, 'etudiant_id' => $this->a->id, 'etat' => 'present']);
        $rappels = app(RappelsPointage::class);

        $rappels->dernieresChances($this->a('09:59'));
        Notification::assertNothingSent();

        // Fermeture à 10:15 : la relance part à 10:00.
        $rappels->dernieresChances($this->a('10:00'));
        Notification::assertSentTo($this->b, RappelPointage::class, $this->sousType(RappelPointage::DERNIERE_CHANCE));
        Notification::assertNotSentTo([$this->a, $this->restreint, $this->delegue], RappelPointage::class);

        $rappels->dernieresChances($this->a('10:05'));
        Notification::assertSentToTimes($this->b, RappelPointage::class, 1);
    }

    public function test_nothing_is_sent_after_closing_or_on_another_day(): void
    {
        $this->position();
        $rappels = app(RappelsPointage::class);

        $rappels->tick($this->a('10:16'));
        Carbon::setTestNow(Carbon::parse('2026-09-15 08:00', 'Africa/Douala'));
        $rappels->tick(now());

        Notification::assertNothingSent();
    }

    // --- canaux et contenu --------------------------------------------------

    public function test_reminder_is_stored_for_the_in_app_bell_and_pushed_only_with_vapid_keys(): void
    {
        $this->seance->load(['salle', 'courseTemplate.matiere']);

        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        $sansPush = new RappelPointage($this->seance, RappelPointage::OUVERTURE);
        $this->assertSame(['database'], $sansPush->via($this->a));

        config(['webpush.vapid.public_key' => 'pub', 'webpush.vapid.private_key' => 'priv']);
        $this->assertContains(WebPushChannel::class, $sansPush->via($this->a));

        $donnees = $sansPush->toArray($this->a);
        $this->assertSame('rappel_pointage', $donnees['type']);
        $this->assertSame('ouverture', $donnees['sous_type']);
        $this->assertSame('Le pointage est ouvert', $donnees['titre']);
        $this->assertStringContainsString('08:00–10:00', $donnees['message']);
        $this->assertStringContainsString('avant 10:15', $donnees['message']);
        $this->assertStringContainsString($this->salle->nom, $donnees['message']);

        $push = $sansPush->toWebPush($this->a, $sansPush)->toArray();
        $this->assertSame('Le pointage est ouvert', $push['title']);
        $this->assertSame('seance-'.$this->seance->id, $push['tag']);
        $this->assertSame('/dashboard', $push['data']['url']);
    }

    public function test_the_command_reports_what_it_sent(): void
    {
        $this->a('07:50');

        $this->artisan('presence:rappels')
            ->expectsOutputToContain('délégués : 1 · ouverture : 0 · dernière chance : 0')
            ->assertSuccessful();
    }
}
