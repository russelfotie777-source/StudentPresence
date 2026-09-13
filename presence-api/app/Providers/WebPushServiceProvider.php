<?php

namespace App\Providers;

use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\ReportHandlerInterface;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushServiceProvider as FournisseurDuPaquet;

/**
 * Remplace le fournisseur du paquet webpush pour donner un logger au client
 * Web Push. Sans logger, la bibliothèque signale l'absence des extensions
 * GMP/BCMath par un trigger_error() — que Laravel transforme en exception :
 * chaque envoi de position par un délégué échouait en 500 sur un serveur
 * sans ces extensions. Avec le logger, la remarque part dans les journaux
 * et l'envoi continue (plus lentement ; installez php-bcmath ou php-gmp).
 */
class WebPushServiceProvider extends FournisseurDuPaquet
{
    public function boot(): void
    {
        $config = $this->webPushConfig();

        $this->app->when(WebPushChannel::class)
            ->needs(WebPush::class)
            ->give(fn (): WebPush => (new WebPush(
                $this->webPushAuth(),
                [],
                $this->webPushClient($config['client_options']),
                new HttpFactory,
                new HttpFactory,
                null,
                Log::channel(),
            ))
                ->setReuseVAPIDHeaders(true)
                ->setAutomaticPadding($config['automatic_padding']));

        $this->app->when(WebPushChannel::class)
            ->needs(ReportHandlerInterface::class)
            ->give(ReportHandler::class);

        if ($this->app->runningInConsole()) {
            $this->definePublishing();
        }
    }
}
