<?php

namespace App\Rabbitmq\Console\Commands;

use App\Rabbitmq\Rabbit\Client;
use Illuminate\Console\Command;

class RabbitMoveCommand extends Command
{
    public function __construct(
        private readonly Client $client,
    )
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbit:move {from-queue} {to-queue} {--method=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To move message to other queue';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $method = $this->option('method');
        $toQueue = $this->argument('to-queue');
        $fromQueue = $this->argument('from-queue');

        $info = [
            'total' => 0,
            'effected' => 0,
        ];

        $channel = $this->client->getChannel();

        while ($message = $channel->basic_get($fromQueue)) {
            $info['total']++;

            $data = $this->client->unserialize($message->getBody());
            if ($method && data_get($data, 'method', false) !== $method) {
                continue;
            }

            $this->client
                ->setMessage($data)
                ->publish($toQueue);

            $message->ack();
            $info['effected']++;
        }

        $this->client->getChannel()->close();
        $this->client->getConnection()->close();

        $this->info(
            "Messages are moved!\n".
            "- Total: " . $info['total'] . "\n".
            "- Moved: " . $info['effected']
        );
    }
}
