<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Contracts;

interface EventBusInterface
{
    public function publish(string $exchange, IntegrationEventInterface $event): void;

    public function close(): void;
}
