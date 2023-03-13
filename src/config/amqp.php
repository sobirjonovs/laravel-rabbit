<?php

use App\Rabbitmq\FailedJob\FailedJobHandlerToFile;

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
    "failed_job_handler" => FailedJobHandlerToFile::class,
    // Default device name
    'default_device' => 'mobile',
    'device_parameter_name' => '_x_device'
];