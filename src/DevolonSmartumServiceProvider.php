<?php

namespace Devolon\Smartum;

use Devolon\Payment\Contracts\PaymentGatewayInterface;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class DevolonSmartumServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (env('IS_SMARTUM_AVAILABLE', false)) {
            $this->app->tag(SmartumGateway::class, PaymentGatewayInterface::class);
        }

        $this->app->singleton(SmartumClient::class, function () {
            $venue = config('smartum.venue');
            $url = config('smartum.url');
            $client = new Client([
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
            return new SmartumClient($venue, $url, $client);
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/smartum.php', 'smartum');
        $this->publishes([
            __DIR__ . '/../config/smartum.php' => config_path('smartum.php')
        ], 'smartum-config');
        $this->publishes([
            __DIR__ . '/../config/smartum-public.key' => storage_path('smartum-public.key')
        ], 'smartum-key');
    }
}
