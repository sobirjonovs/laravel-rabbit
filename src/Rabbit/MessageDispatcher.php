<?php

namespace App\Rabbitmq\Rabbit;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Log;

class MessageDispatcher
{
    /**
     * @var array $methods
     */
    protected $methods = [];

    /**
     * @var Application $app
     */
    protected Application $app;

    /**
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->app = $application;
        $this->methods = config('rabbit_events', []);
    }

    /**
     * @param string $name
     * @param array $parameters
     * @return mixed|void
     */
    public function dispatch(string $name, array $parameters)
    {
        try {
            $methods = $this->methods;
            $method = data_get($methods, $name);

            if (null === $method) {
                throw new Exception("The current method [$name] not found");
            }

            $method = $this->methods[$method];

            $object = $this->app->make(data_get($method, 'dto'), ['parameters' => $parameters]);

            $callback = [$this->app->make(data_get($method, 'class')), data_get($method, 'action')];

            return $this->app->call($callback, ['object' => $object]);
        } catch (BindingResolutionException|Exception $exception) {
            Log::error("Exception: [{$exception->getMessage()}]");
        }
    }
}
