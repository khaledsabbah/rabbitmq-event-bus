<?php

declare(strict_types=1);

namespace Idaratech\EventBus;

use Idaratech\EventBus\Consumer\ConsumeCommand;
use Idaratech\EventBus\Consumer\ReplayDlqCommand;
use Idaratech\EventBus\Contracts\ContextProviderInterface;
use Idaratech\EventBus\Contracts\EventBusInterface;
use Illuminate\Support\ServiceProvider;

class EventBusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/eventbus.php',
            'eventbus',
        );

        $this->app->singleton(EventBusInterface::class, function ($app) {
            $driver = config('eventbus.driver', 'null');

            if ($driver === 'rabbitmq') {
                $connection = config('eventbus.connection');

                return new RabbitMqEventBus(
                    host: $connection['host'],
                    port: (int) $connection['port'],
                    user: $connection['user'],
                    password: $connection['password'],
                    vhost: $connection['vhost'] ?? '/',
                    heartbeat: (int) ($connection['heartbeat'] ?? 30),
                    connectionTimeout: (float) ($connection['connection_timeout'] ?? 10.0),
                    readWriteTimeout: (float) ($connection['read_write_timeout'] ?? 60.0),
                );
            }

            return new NullEventBus();
        });

        $contextProviderClass = config('eventbus.context_provider');

        if ($contextProviderClass !== null) {
            $this->app->singleton(ContextProviderInterface::class, $contextProviderClass);
        }

        $this->app->singleton(IntegrationEventFactory::class, function ($app) {
            return new IntegrationEventFactory(
                contextProvider: $app->make(ContextProviderInterface::class),
                source: config('eventbus.source', 'unknown'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/eventbus.php' => config_path('eventbus.php'),
        ], 'eventbus-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsumeCommand::class,
                ReplayDlqCommand::class,
            ]);
        }
    }
}
