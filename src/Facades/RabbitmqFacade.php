<?php

namespace App\Rabbitmq\Facades;

use App\Rabbitmq\Rabbit\MessageDispatcher;
use Illuminate\Support\Facades\Facade;

class RabbitmqFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return MessageDispatcher::class;
    }
}
