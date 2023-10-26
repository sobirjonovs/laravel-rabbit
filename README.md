# Installation

```php
composer require sobirjonovs/laravel-rabbit
```

## Load configuration files

```php
php artisan vendor:publish --tag=rabbit-configs
```

## Load service provider

```php
php artisan vendor:publish --tag=rabbit-providers
```

## Settings

- `config/amqp.php` - RabbitMQ settings
- `config/rabbit_events.php` - Write methods what they are responsible for the events dispatched from another
  microservice
- `app/Providers/RabbitServicePRovider` - Write queues what to be declared at runtime

# Examples

## 1. Consuming messages

```php
<?php

use App\Rabbitmq\Rabbit\Client;
use PhpAmqpLib\Message\AMQPMessage;
    
$client = app(Client::class);
$client->consume('queue-one', function (AMQPMessage $message) {
            /**
             * Acknowledge a message
             */
            $message->ack(true);
            
            /**
             * @var Client $this
             */
            $this->dispatchEvents($message);
        })->wait(); // or waitForever();

?>
```

## 2. Publishing message

```php
<?php

use App\Rabbitmq\Rabbit\Client;
use PhpAmqpLib\Message\AMQPMessage;
    
$client = app(Client::class);
$client->setMessage([
            'method' => $method,
            'params' => $object->all()
])->publish('queue-one');

?>
```

## 3. Publishing and consuming a message at once

```php
<?php

use App\Rabbitmq\Rabbit\Client;
use PhpAmqpLib\Message\AMQPMessage;
    
$client = app(Client::class);
$result = $client->setMessage([
            'method' => 'methodName',
            'params' => ['param1' => 'value1']
])->request()->getResult(); # you can pass a queue name inside a request method, otherwise it uses the default queue

?>
```

# Development

You can register your events in `config/rabbit_events.php` like that

```bash
<?php

use App\Rabbitmq\Dto\Products\ProductServiceObject;
use App\Rabbitmq\Services\ProductService;

return [
    'createProduct' => [
        'class' => ProductService::class,
        'method' => 'createProduct',
        'dto' => ProductServiceObject::class
    ]
];
```

Now you need a new service and DTO (Data Transfer Object) classes.

To create a new service class with a function, use the following command:

```bash
php artisan make:rabbit-service ProductService createProduct
```

This will create a new service class named `ProductService` with a function named `createProduct`.

It also creates DTO class which will accepted in `createProduct` function.

The generated files will be located in the following directories:
- Service class: App/Services/ProductService.php
- Data Transfer Object (DTO): App/Services/Dto/ProductServiceObject.php

If you want to change namespace of service and DTO classes, you can replace them in `config/amqp.php`.

```bash
<?php
    .
    .
    .
    /**
     * Namespace of data service classes
     *
     * It will be merged to root namespace which is App/config('amqp.service_namespace')
     */
    'service_namespace' => 'Services',

    /**
     * Namespace of data transfer object
     *
     * It will be merged to root namespace which is App/config('amqp.service_namespace')/config('amqp.dto_namespace')
     */
    'dto_namespace' => 'Dto',
];
```



