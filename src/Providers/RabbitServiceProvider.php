<?php

namespace App\Rabbitmq\Providers;

use App\Rabbitmq\Console\Commands\DataObjectMakeCommand;
use App\Rabbitmq\Console\Commands\InstallCommand;
use App\Rabbitmq\Console\Commands\RabbitMoveCommand;
use App\Rabbitmq\Console\Commands\ServiceMakeCommand;
use App\Rabbitmq\Rabbit\MessageDispatcher;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;

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
            ], 'rabbit-configs');

            $this->publishes([
                $providerPath . 'RabbitServiceProvider.stub' => app_path('Providers/RabbitServiceProvider.php')
            ], 'rabbit-providers');

            $this->commands([
                DataObjectMakeCommand::class,
                ServiceMakeCommand::class,
                RabbitMoveCommand::class,
                InstallCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/amqp.php', 'amqp');
    }
}
