<?php

namespace App\Rabbitmq\Facades;

use Illuminate\Support\Facades\Facade;

class RabbitmqFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'rabbitmq.dispatcher';
    }
}
