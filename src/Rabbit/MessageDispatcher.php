<?php

namespace App\Rabbitmq\Rabbit;

use Log;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Container\BindingResolutionException;

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
     */
    public function dispatch(string $name, array $parameters)
    {
        try {
            $methods = $this->methods;
            $data = [];
            $method = data_get($methods, $name);

            if (null === $method) {
                throw new Exception("The current method [$name] not found");
            }

            $dto = data_get($method, 'dto');

            if (null !== $dto) {
                $data['object'] = $this->app->make($dto, ['parameters' => $parameters]);
            }

            $callback = [$this->app->make(data_get($method, 'class')), data_get($method, 'method')];

            return $this->app->call($callback, $data);
        } catch (ValidationException $validationException) {
            return [
                'success' => false,
                'message' => $validationException->errors()
            ];
        } catch (BindingResolutionException|Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
