<?php

namespace App\Rabbitmq\Rabbit;

use Illuminate\Contracts\Foundation\Application;
use Exception;

class MessageDispatcher
{
    /**
     * @var array $methods
     */
    protected $methods = [];

    /**
     * @var Application $app
     */
    protected $app;

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
     * @throws Exception
     */
    public function dispatch(string $name, array $parameters)
    {
        $methods = $this->methods;
        $data = [];
        $method = data_get($methods, $name);

        if (null === $method) {
            throw new Exception("The current method [$name] not found");
        }

        $dto = data_get($method, 'dto');

        if (null !== $dto) {
            $data['object'] = $this->app->make($dto, ['parameters' => $parameters]);
        } else {
            $data = $parameters;
        }

        $callback = [$this->app->make(data_get($method, 'class')), data_get($method, 'method')];

        return $this->app->call($callback, $data);
    }
}
