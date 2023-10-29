<?php

namespace App\Rabbitmq\Exceptions;

use App\Rabbitmq\Rabbit\Client;
use Throwable;

class DeadLetterHandler
{
    /**
     * When error is happened, the function sends message to dead letter queue
     *
     * @param array $data
     * @param Throwable $exception
     * @param Client $client
     * @throws \Exception
     */
    public static function toQueue(array $data, Throwable $exception, Client $client): void
    {
        $data['error'] = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => $exception->getMessage(),
            'time' => date('Y-m-d H:i:s'),
        ];

        $client->setMessage($data)->publish(config('amqp.dead_letter_queue'));
    }
}
