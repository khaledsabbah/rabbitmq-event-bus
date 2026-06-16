<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Contracts;

interface EventHandlerInterface
{
    public function handle(IntegrationEventInterface $event): void;

    public function supports(string $eventType): bool;
}
