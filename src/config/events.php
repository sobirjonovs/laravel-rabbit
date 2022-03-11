<?php

use App\Rabbitmq\Dto\Products\ProductCreateObject;
use App\Rabbitmq\Services\ProductService;

return [
    'createProduct' => [
        'class' => ProductService::class,
        'action' => 'createProduct',
        'dto' => ProductCreateObject::class
    ]
];