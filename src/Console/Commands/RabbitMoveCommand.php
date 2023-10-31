<?php

namespace App\Rabbitmq\Console\Commands;

use App\Rabbitmq\Rabbit\Client;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitMoveCommand extends Command
{
    private AMQPChannel $channel;
    public function __construct(
        private readonly Client $client,
    )
    {
        parent::__construct();

        $this->channel = $this->client->getChannel();
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
        $to = $this->argument('to-queue');
        $from = $this->argument('from-queue');

        $info = [
            'total' => 0,
            'effected' => 0,
        ];

        while ($message = $this->channel->basic_get($from)) {
            $info['total']++;

            $data = $this->client->unserialize($message->getBody());
            if ($method and data_get($data, 'method', false) !== $method) {
                continue;
            }

            $this->client
                ->setMessage($data)
                ->publish($to);

            $message->ack();
            $info['effected']++;
        }

        $this->info(
            'Messages are moved!' . chr(10) .
            '- Total: ' . $info['total'] . chr(10) .
            '- Moved: ' . $info['effected'] . chr(10)
        );
    }
}
