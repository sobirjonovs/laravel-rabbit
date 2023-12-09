<?php

namespace App\Rabbitmq\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbit:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the package resources';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->comment('Publishing Rabbit Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbit-configs']);

        $this->comment('Publishing Telescope Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'rabbit-providers']);

        $this->registerRabbitServiceProvider();
    }

    private function registerRabbitServiceProvider(): void
    {
        $appConfig = file_get_contents(config_path('app.php'));
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        if (Str::contains($appConfig, $namespace.'\\Providers\\RabbitServiceProvider::class')) {
            return;
        }

        $lineEndingCount = [
            "\r\n" => substr_count($appConfig, "\r\n"),
            "\r" => substr_count($appConfig, "\r"),
            "\n" => substr_count($appConfig, "\n"),
        ];

        $eol = array_keys($lineEndingCount, max($lineEndingCount))[0];

        file_put_contents(config_path('app.php'), str_replace(
            "{$namespace}\\Providers\RouteServiceProvider::class,".$eol,
            "{$namespace}\\Providers\RouteServiceProvider::class,".$eol
            ."        {$namespace}\Providers\RabbitServiceProvider::class,".$eol,
            $appConfig
        ));
    }
}
