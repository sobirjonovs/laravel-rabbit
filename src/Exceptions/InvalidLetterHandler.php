<?php

namespace App\Rabbitmq\Exceptions;

use App\Rabbitmq\Rabbit\Client;
use Illuminate\Validation\ValidationException;

class InvalidLetterHandler
{
    public function toQueue(array &$data, ValidationException $validationException, Client $client): void
    {
        $data['validation'] = $validationException->errors();

        $client->setMessage($data)->publish(config('amqp.invalid_letter_queue'));

    }
}
