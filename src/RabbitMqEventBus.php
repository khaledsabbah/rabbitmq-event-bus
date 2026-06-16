<?php

declare(strict_types=1);

namespace Idaratech\EventBus;

use Idaratech\EventBus\Contracts\EventBusInterface;
use Idaratech\EventBus\Contracts\IntegrationEventInterface;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMqEventBus implements EventBusInterface
{
    private ?AMQPLazyConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $vhost = '/',
        private readonly int $heartbeat = 30,
        private readonly float $connectionTimeout = 10.0,
        private float $readWriteTimeout = 60.0,
    ) {
        $minTimeout = $this->heartbeat * 2;
        if ($this->readWriteTimeout < $minTimeout) {
            $this->readWriteTimeout = (float) $minTimeout;
        }
    }

    public function publish(string $exchange, IntegrationEventInterface $event): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $channel = $this->getChannel();

                $message = new AMQPMessage(
                    $event->toJson(),
                    [
                        'content_type' => 'application/json',
                        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        'message_id' => $event->getEventId(),
                        'app_id' => $event->getSource(),
                    ],
                );

                $channel->basic_publish(
                    $message,
                    $exchange,
                    $event->getEventType(),
                );

                Log::debug('EventBus: published event', [
                    'exchange' => $exchange,
                    'event_type' => $event->getEventType(),
                    'event_id' => $event->getEventId(),
                ]);

                return;
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->resetConnection();

                if ($attempt < 2) {
                    Log::warning('EventBus: publish failed, retrying', [
                        'exchange' => $exchange,
                        'event_type' => $event->getEventType(),
                        'event_id' => $event->getEventId(),
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    usleep(500_000);
                }
            }
        }

        Log::error('EventBus: failed to publish event after retries', [
            'exchange' => $exchange,
            'event_type' => $event->getEventType(),
            'event_id' => $event->getEventId(),
            'error' => $lastException->getMessage(),
        ]);

        throw $lastException;
    }

    public function close(): void
    {
        try {
            if ($this->channel !== null && $this->channel->is_open()) {
                $this->channel->close();
            }
        } catch (\Throwable) {
            // Ignore close errors
        }

        try {
            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (\Throwable) {
            // Ignore close errors
        }

        $this->channel = null;
        $this->connection = null;
    }

    private function getChannel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return $this->channel;
        }

        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPLazyConnection(
                host: $this->host,
                port: $this->port,
                user: $this->user,
                password: $this->password,
                vhost: $this->vhost,
                heartbeat: $this->heartbeat,
                connection_timeout: $this->connectionTimeout,
                read_write_timeout: $this->readWriteTimeout,
            );
        }

        $this->channel = $this->connection->channel();

        return $this->channel;
    }

    private function resetConnection(): void
    {
        try {
            $this->channel?->close();
        } catch (\Throwable) {
            // Ignore
        }

        try {
            $this->connection?->close();
        } catch (\Throwable) {
            // Ignore
        }

        $this->channel = null;
        $this->connection = null;
    }
}
