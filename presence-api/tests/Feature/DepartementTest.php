<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Semaine;
use App\Models\User;
use App\Services\ListeHebdomadaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le département coiffe filières et salles : il s'ouvre à tous les niveaux
 * d'office, tout se filtre par lui, et les listes de présence sortent à
 * son nom — celui de la salle imprimée, pas un nom fixé pour tout le monde.
 */
class DepartementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_departement_opens_a_filiere_at_every_niveau(): void
    {
        foreach (['L1', 'L2', 'L3'] as $nom) {
            Niveau::factory()->create(['nom' => $nom]);
        }

        $reponse = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->postJson('/api/departements', ['nom' => 'Génie des Réseaux et Télécommunications', 'code' => 'grt'])
            ->assertCreated()
            ->assertJsonPath('code', 'GRT')
            ->assertJsonCount(3, 'niveaux')
            ->assertJsonPath('niveaux.0.filieres.0.nom', 'Génie des Réseaux et Télécommunications');

        $departement = Departement::findOrFail($reponse->json('id'));

        $this->assertSame(
            ['L1', 'L2', 'L3'],
            $departement->filieres()->with('niveau')->get()->pluck('niveau.nom')->sort()->values()->all(),
        );
        $this->assertTrue(
            $departement->filieres->every(fn (Filiere $f) => $f->nom === 'Génie des Réseaux et Télécommunications'),
            'La filière ouverte à chaque niveau porte le nom du département.',
        );
    }

    public function test_the_tree_lists_every_niveau_even_without_filiere(): void
    {
        $departement = Departement::factory()->create();
        $l1 = Niveau::factory()->create(['nom' => 'L1']);
        Niveau::factory()->create(['nom' => 'L2']);
        $filiere = Filiere::factory()->create(['departement_id' => $departement->id, 'niveau_id' => $l1->id]);
        Salle::factory()->create(['filiere_id' => $filiere->id, 'nom' => 'A23', 'formation' => FormationType::FI]);

        $this->getJson("/api/departements/{$departement->id}")
            ->assertOk()
            ->assertJsonCount(2, 'niveaux')
            ->assertJsonPath('niveaux.0.nom', 'L1')
            ->assertJsonPath('niveaux.0.filieres.0.salles.0.nom', 'A23')
            ->assertJsonPath('niveaux.1.nom', 'L2')
            ->assertJsonCount(0, 'niveaux.1.filieres')
            ->assertJsonPath('salles_count', 1);
    }

    public function test_filieres_and_salles_can_be_listed_by_departement(): void
    {
        $gi = Departement::factory()->create(['code' => 'GI']);
        $grt = Departement::factory()->create(['code' => 'GRT']);
        $niveau = Niveau::factory()->create(['nom' => 'L3']);
        $filiereGI = Filiere::factory()->create(['departement_id' => $gi->id, 'niveau_id' => $niveau->id]);
        $filiereGRT = Filiere::factory()->create(['departement_id' => $grt->id, 'niveau_id' => $niveau->id]);
        Salle::factory()->create(['filiere_id' => $filiereGI->id, 'nom' => 'A23']);
        Salle::factory()->create(['filiere_id' => $filiereGRT->id, 'nom' => 'D4']);

        $this->getJson("/api/filieres?departement_id={$grt->id}")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $filiereGRT->id);

        $this->getJson("/api/salles?departement_id={$grt->id}")
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.nom', 'D4')
            ->assertJsonPath('0.filiere.departement.code', 'GRT');

        $this->getJson('/api/departements')
            ->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.salles_count', 1);
    }

    public function test_a_filiere_needs_a_departement_and_the_same_name_may_exist_in_two(): void
    {
        $admin = User::factory()->admin()->create();
        $niveau = Niveau::factory()->create(['nom' => 'L3']);
        [$gi, $grt] = Departement::factory()->count(2)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/filieres', ['nom' => 'Informatique', 'niveau_id' => $niveau->id])
            ->assertUnprocessable()->assertJsonValidationErrors('departement_id');

        foreach ([$gi, $grt] as $departement) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/filieres', ['nom' => 'Informatique', 'niveau_id' => $niveau->id, 'departement_id' => $departement->id])
                ->assertCreated();
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/filieres', ['nom' => 'Informatique', 'niveau_id' => $niveau->id, 'departement_id' => $gi->id])
            ->assertUnprocessable()->assertJsonValidationErrors('nom');
    }

    public function test_weekly_list_header_names_the_departement_of_the_salle(): void
    {
        $grt = Departement::factory()->create(['nom' => 'Génie des Réseaux et Télécommunications', 'code' => 'GRT', 'nom_en' => 'Networks Engineering']);
        $niveau = Niveau::factory()->create(['nom' => 'L2']);
        $tronc = Filiere::factory()->create(['departement_id' => $grt->id, 'niveau_id' => $niveau->id, 'nom' => $grt->nom]);
        $salle = Salle::factory()->create(['filiere_id' => $tronc->id, 'formation' => FormationType::FI]);
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);

        $donnees = app(ListeHebdomadaire::class)->pour($salle, $semaine);

        $this->assertSame('DEPARTEMENT DE GENIE DES RESEAUX ET TELECOMMUNICATIONS', $donnees['departement_fr']);
        $this->assertSame('DEPARTMENT OF NETWORKS ENGINEERING', $donnees['departement_en']);
        // La filière tronc commun porte le code du département, pas un sigle recalculé.
        $this->assertSame('GRT', $donnees['option']);
        $this->assertSame('GRT2 – FI', $donnees['groupe']);
    }

    public function test_official_header_elides_before_a_vowel_and_falls_back_without_translation(): void
    {
        $departement = Departement::factory()->make(['nom' => 'Informatique', 'nom_en' => null]);

        $this->assertSame("DEPARTEMENT D'INFORMATIQUE", $departement->enteteFr());
        $this->assertSame('DEPARTMENT OF INFORMATIQUE', $departement->enteteEn());
    }

    public function test_departement_pdf_prints_one_list_per_salle_for_admin_only(): void
    {
        $departement = Departement::factory()->create(['code' => 'GI']);
        $niveau = Niveau::factory()->create(['nom' => 'L3']);
        $filiere = Filiere::factory()->create(['departement_id' => $departement->id, 'niveau_id' => $niveau->id]);
        Salle::factory()->create(['filiere_id' => $filiere->id, 'nom' => 'A23', 'formation' => FormationType::FI]);
        Salle::factory()->create(['filiere_id' => $filiere->id, 'nom' => 'D4', 'formation' => FormationType::FA]);
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);

        $donnees = app(ListeHebdomadaire::class)->pourDepartement($departement, $semaine);
        $this->assertSame(['A23', 'D4'], $donnees['listes']->pluck('salle')->all(), 'FI avant FA.');

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->get("/api/departements/{$departement->id}/liste-presence.pdf?semaine_id={$semaine->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::factory()->enseignant()->create(), 'sanctum')
            ->get("/api/departements/{$departement->id}/liste-presence.pdf?semaine_id={$semaine->id}")
            ->assertForbidden();
    }

    public function test_departement_pdf_refuses_a_departement_without_salle(): void
    {
        $departement = Departement::factory()->create();
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson("/api/departements/{$departement->id}/liste-presence.pdf?semaine_id={$semaine->id}")
            ->assertUnprocessable();
    }

    public function test_deleting_a_departement_takes_its_filieres_and_salles_along(): void
    {
        $departement = Departement::factory()->create();
        $filiere = Filiere::factory()->create(['departement_id' => $departement->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->deleteJson("/api/departements/{$departement->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('filieres', ['id' => $filiere->id]);
        $this->assertDatabaseMissing('salles', ['id' => $salle->id]);
    }

    public function test_managing_departements_requires_admin(): void
    {
        $this->postJson('/api/departements', ['nom' => 'GI', 'code' => 'GI'])->assertUnauthorized();

        $this->actingAs(User::factory()->enseignant()->create(), 'sanctum')
            ->postJson('/api/departements', ['nom' => 'GI', 'code' => 'GI'])
            ->assertForbidden();
    }
}
