<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private const ORIGINS = [
        'https://app.example.com',
        'https://admin.example.com',
    ];

    public function test_configured_frontend_origin_is_allowed(): void
    {
        config(['cors.allowed_origins' => self::ORIGINS]);

        $this->withHeader('Origin', 'https://app.example.com')
            ->getJson('/api/heure')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
    }

    public function test_unknown_frontend_origin_is_not_allowed(): void
    {
        config(['cors.allowed_origins' => self::ORIGINS]);

        $this->withHeader('Origin', 'https://malveillant.example')
            ->getJson('/api/heure')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
