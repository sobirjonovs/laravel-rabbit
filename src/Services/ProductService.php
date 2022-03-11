<?php

namespace App\Rabbitmq\Services;

use App\Rabbitmq\Dto\Products\ProductCreateObject;
use App\Rabbitmq\Rabbit\Client;

class ProductService
{
    /**
     * @var Client $client
     */
    protected $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * @param ProductCreateObject $object
     * @return array
     */
    public function createProduct(ProductCreateObject $object): array
    {
        return ['created'];
    }
}
