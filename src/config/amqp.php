<?php

return [
    'host'                  => env('AMQP_HOST', 'host.docker.internal'),
    'port'                  => env('AMQP_PORT', 5672),
    'username'              => env('AMQP_USER', 'guest'),
    'password'              => env('AMQP_PASSWORD', 'guest'),
    'vhost'                 => env('AMQP_VHOST', '/'),
    'qos'                   => true,
    'qos_prefetch_size'     => 0,
    'qos_prefetch_count'    => 2,
    'qos_a_global'          => false,
];
