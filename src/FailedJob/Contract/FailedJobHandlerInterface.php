<?php
namespace App\Rabbitmq\FailedJob\Contract;

interface FailedJobHandlerInterface
{
    public function write(array $data);

    public function run();
}