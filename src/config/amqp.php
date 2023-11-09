<?php

return [
    'host' => env('AMQP_HOST', 'host.docker.internal'),
    'port' => env('AMQP_PORT', 5672),
    'username' => env('AMQP_USER', 'guest'),
    'password' => env('AMQP_PASSWORD', 'guest'),
    'vhost' => env('AMQP_VHOST', '/'),
    'insist' => false,
    'qos' => true,
    'qos_prefetch_size' => 0,
    'qos_prefetch_count' => 1,
    'qos_a_global' => false,
    'read_and_write_timeout' => 30,
    'connection_timeout' => 60,
    'login_method' => 'AMQPLAIN',
    'locale' => 'en_US',
    'login_response' => null,
    'context' => null,
    'heartbeat' => 0,
    'keepalive' => true,
    'channel_rpc_timeout' => 30,

    // Default device name
    'default_device' => 'mobile',
    'device_parameter_name' => '_x_device',

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

    /**
     * When error is out in service class, that message will be sent to config('amqp.dead-letter-queue')
     *
     * This works only subscriber and publisher mode
     */
    'dead_letter_queue' => 'dead-letter-queue',

    /**
     * When message cannot pass validation, the message will be sent to this queue
     * If name of this queue is null, the message will be deleted
     *
     * This works only subscriber and publisher mode
     */
    'invalid_letter_queue' => null,
];
