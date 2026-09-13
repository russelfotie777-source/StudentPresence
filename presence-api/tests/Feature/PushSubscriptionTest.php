<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_key_endpoint_tells_whether_push_is_available(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        $this->getJson('/api/push/cle-publique')->assertOk()->assertJson(['cle' => null, 'disponible' => false]);

        config(['webpush.vapid.public_key' => 'BPUB', 'webpush.vapid.private_key' => 'PRIV']);
        $this->getJson('/api/push/cle-publique')->assertOk()->assertJson(['cle' => 'BPUB', 'disponible' => true]);
    }

    public function test_a_device_can_subscribe_and_unsubscribe(): void
    {
        $etudiant = User::factory()->etudiant()->create();
        $abonnement = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'p256dh-cle', 'auth' => 'auth-cle'],
            'content_encoding' => 'aes128gcm',
        ];

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/me/push-subscriptions', $abonnement)
            ->assertCreated()
            ->assertJson(['abonne' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $etudiant->id,
            'endpoint' => $abonnement['endpoint'],
            'content_encoding' => 'aes128gcm',
        ]);

        // Le même appareil qui se réabonne ne crée pas de doublon.
        $this->actingAs($etudiant, 'sanctum')->postJson('/api/me/push-subscriptions', $abonnement)->assertCreated();
        $this->assertDatabaseCount('push_subscriptions', 1);

        $this->actingAs($etudiant, 'sanctum')
            ->deleteJson('/api/me/push-subscriptions', ['endpoint' => $abonnement['endpoint']])
            ->assertOk()
            ->assertJson(['abonne' => false]);
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_subscription_requires_the_browser_keys(): void
    {
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/me/push-subscriptions', ['endpoint' => 'https://push.example/x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    public function test_subscribing_requires_authentication(): void
    {
        $this->postJson('/api/me/push-subscriptions', ['endpoint' => 'https://push.example/x'])->assertUnauthorized();
    }
}
