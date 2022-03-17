<?php

use App\Rabbitmq\Dto\Products\ProductCreateObject;
use App\Rabbitmq\Services\ProductService;

return [
    'createProduct' => [
        'class' => ProductService::class,
        'method' => 'createProduct',
        'dto' => ProductCreateObject::class
    ]
];