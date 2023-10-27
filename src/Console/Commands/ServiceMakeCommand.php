<?php

namespace App\Rabbitmq\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class ServiceMakeCommand extends GeneratorCommand
{
    private Str $str;

    public function __construct(Filesystem $files, Str $str)
    {
        parent::__construct($files);

        $this->str = $str;
    }

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:rabbit-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/service.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\'.config('amqp.service_namespace');
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the service.'],
            ['function', InputArgument::REQUIRED, 'The name of the function.'],
        ];
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $object = $this->str->studly($this->argument('name'));
        $function = $this->argument('function');

        $stub = str_replace(['{{ function }}', '{{ object }}'], [$function, "{$object}Object"], $stub);

        $stub = str_replace(
            '{{ objectNamespace }}',
            config('amqp.service_namespace').'\\'.config('amqp.dto_namespace')."\\{$object}Object",
            $stub
        );

        return $stub;
    }

    /**
     * Execute the console command.
     *
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(): ?bool
    {
        $this->createDataObject();

        return parent::handle();
    }

    private function createDataObject(): void
    {
        $object = Str::studly($this->argument('name'));

        $this->call('make:rabbit-object', ['name' => "{$object}Object"]);
    }
}
