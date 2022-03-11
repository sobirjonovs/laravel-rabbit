<?php

namespace App\Rabbitmq\Contracts;

use Closure;

interface RabbitContract
{
    /**
     * @param string $queue
     * @return mixed
     */
    public function request(string $queue = 'queue'): self;

    /**
     * @param string $queue
     * @param string $exchange
     * @return mixed
     */
    public function publish(string $queue, string $exchange = ''): self;

    /**
     * @param string $queue
     * @param Closure $callback
     * @return mixed
     */
    public function consume(string $queue, Closure $callback): self;
}
