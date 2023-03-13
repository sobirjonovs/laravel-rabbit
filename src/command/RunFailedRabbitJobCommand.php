<?php

namespace App\Rabbitmq\command;

use App\Rabbitmq\FailedJob\Contract\FailedJobHandlerInterface;
use Illuminate\Console\Command;

class RunFailedRabbitJobCommand extends Command
{


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'rabbit-mq:run-failed-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(FailedJobHandlerInterface $failedJobHandler): void
    {
        $failedJobHandler->run();
    }
}
