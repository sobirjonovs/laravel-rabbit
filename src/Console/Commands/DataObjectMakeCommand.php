<?php

namespace App\Rabbitmq\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class DataObjectMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:rabbit-object';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new DTO object class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Data Object';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/object.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\'.config('amqp.service_namespace').'\\'.config('amqp.dto_namespace');
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the service.'],
        ];
    }
}
