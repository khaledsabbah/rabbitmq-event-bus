<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Event Bus Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "rabbitmq", "null"
    | Defaults to "rabbitmq" if RABBITMQ_HOST is set, otherwise "null".
    |
    */
    'driver' => env('EVENTBUS_DRIVER', env('RABBITMQ_HOST') ? 'rabbitmq' : 'null'),

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection
    |--------------------------------------------------------------------------
    */
    'connection' => [
        'host' => env('RABBITMQ_HOST', ''),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'heartbeat' => env('RABBITMQ_HEARTBEAT', 30),
        'connection_timeout' => env('RABBITMQ_CONNECTION_TIMEOUT', 10.0),
        'read_write_timeout' => env('RABBITMQ_READ_WRITE_TIMEOUT', 60.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Identifier
    |--------------------------------------------------------------------------
    |
    | Used as the "source" field in integration events published by this service.
    |
    */
    'source' => env('EVENTBUS_SOURCE', 'unknown'),

    /*
    |--------------------------------------------------------------------------
    | Context Provider
    |--------------------------------------------------------------------------
    |
    | FQCN of the class implementing ContextProviderInterface.
    | Each host app registers its own implementation that resolves tenant,
    | workspace, actor, and correlation from the current request context.
    |
    | Example: App\EventBus\AppContextProvider::class
    |
    */
    'context_provider' => null,

    /*
    |--------------------------------------------------------------------------
    | Consumer Configurations
    |--------------------------------------------------------------------------
    |
    | Each entry defines a queue to consume from, along with retry/DLQ settings.
    |
    | Example:
    | [
    |     'queue' => 'hr-events',
    |     'retryExchange' => 'hr-events.retry',
    |     'dlqRoutingKey' => 'hr-events.dlq',
    |     'maxRetries' => 3,
    |     'retryDelays' => [1000, 5000, 25000],
    |     'prefetchCount' => 1,
    | ]
    |
    */
    'consumers' => [],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Hooks
    |--------------------------------------------------------------------------
    |
    | Callables invoked before/after each message is handled.
    | Signature: fn(IntegrationEventInterface $event): void
    |
    */
    'hooks' => [
        'before_handle' => null,
        'after_handle' => null,
    ],
];
