<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Contracts;

interface IntegrationEventInterface
{
    public function getEventId(): string;

    public function getEventType(): string;

    public function getVersion(): string;

    public function getSource(): string;

    public function getTenantId(): int|string|null;

    public function getWorkspace(): string;

    public function getCorrelationId(): ?string;

    public function getActor(): array;

    public function getPayload(): array;

    public function getTimestamp(): string;

    public function getMetadata(): array;

    public function toArray(): array;

    public function toJson(): string;
}
