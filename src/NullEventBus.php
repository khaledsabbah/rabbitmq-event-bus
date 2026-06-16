<?php

declare(strict_types=1);

namespace Idaratech\EventBus;

use Idaratech\EventBus\Contracts\EventBusInterface;
use Idaratech\EventBus\Contracts\IntegrationEventInterface;

/**
 * In-memory event bus for testing. Does NOT use the Log facade
 * so it works in plain PHPUnit tests without a Laravel app.
 */
class NullEventBus implements EventBusInterface
{
    /** @var array<int, array{exchange: string, event: IntegrationEventInterface}> */
    private array $publishedEvents = [];

    public function publish(string $exchange, IntegrationEventInterface $event): void
    {
        $this->publishedEvents[] = [
            'exchange' => $exchange,
            'event' => $event,
        ];
    }

    public function close(): void
    {
        // No-op
    }

    /**
     * @return array<int, array{exchange: string, event: IntegrationEventInterface}>
     */
    public function getPublishedEvents(): array
    {
        return $this->publishedEvents;
    }

    public function reset(): void
    {
        $this->publishedEvents = [];
    }
}
