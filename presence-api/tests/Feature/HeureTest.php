<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class HeureTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * L'heure de référence est celle de Douala quel que soit le fuseau du
     * serveur : ici l'horloge « système » est volontairement placée en UTC.
     */
    public function test_reference_time_is_douala_whatever_the_server_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 23:30:00', 'UTC'));

        $response = $this->getJson('/api/heure');

        $response->assertOk();
        $this->assertSame('Africa/Douala', $response->json('fuseau'));
        $this->assertSame('2026-09-15T00:30:00+01:00', $response->json('maintenant'));
        // À Douala, on est déjà mardi : la date et le jour suivent le fuseau, pas UTC.
        $this->assertSame('2026-09-15', $response->json('date'));
        $this->assertSame('00:30', $response->json('heure'));
        $this->assertSame('MARDI', $response->json('jour'));
    }

    public function test_reference_time_is_public(): void
    {
        $this->getJson('/api/heure')->assertOk()->assertJsonStructure(['maintenant', 'fuseau', 'date', 'heure', 'jour']);
    }
}
