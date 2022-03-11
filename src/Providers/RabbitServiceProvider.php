<?php

namespace App\Rabbitmq\Providers;

use Illuminate\Support\ServiceProvider;

class RabbitServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/';
        $providerPath = __DIR__ . '/../stubs/';

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $configPath . 'amqp.php' => config_path('amqp.php'),
                $configPath . 'helpers.php' => config_path('rabbit_helpers.php'),
                $configPath . 'events.php' => config_path('rabbit_events.php'),
            ], 'rabbit-config');

            $this->publishes([
                $providerPath . 'RabbitServiceProvider.stub' => app_path('Providers/RabbitmqServiceProvider.php')
            ], 'rabbit-providers');
        }
    }
}