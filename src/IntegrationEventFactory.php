<?php

declare(strict_types=1);

namespace Idaratech\EventBus;

use Idaratech\EventBus\Contracts\ContextProviderInterface;
use Idaratech\EventBus\Contracts\IntegrationEventInterface;

class IntegrationEventFactory
{
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
        private readonly string $source,
    ) {}

    public function make(string $eventType, string $version, array $payload): IntegrationEventInterface
    {
        return new IntegrationEvent(
            eventType: $eventType,
            version: $version,
            source: $this->source,
            tenantId: $this->contextProvider->getTenantId(),
            workspace: $this->contextProvider->getWorkspace(),
            correlationId: $this->contextProvider->getCorrelationId(),
            actor: $this->contextProvider->getActor(),
            payload: $payload,
        );
    }
}
