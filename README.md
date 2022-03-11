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
- `config/rabbit_events.php` - Write methods what they are responsible for the events dispatched from another microservice
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
     * @var Client $this
    */
    $this->dispatchEvents($message);

    $message->ack();
})->wait();

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
], false)->publish('queue-one');

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
