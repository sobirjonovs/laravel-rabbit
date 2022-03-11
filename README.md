# Installation
```php
composer require sobirjonovs/laravel-rabbit
```
# Load configuration files
```php
php artisan vendor:publish --tag=rabbit-configs
```
# Load service provider
```php
php artisan vendor:publish --tag=rabbit-providers
```

# Settings
- config/amqp.php - RabbitMQ settings
- config/rabbit_events.php - Write methods what they are responsible for the events dispatched from another microservice
- app/Providers/RabbitServicePRovider - Write queues what to be declared at runtime

