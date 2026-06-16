<?php

declare(strict_types=1);

namespace Idaratech\EventBus;

use Idaratech\EventBus\Contracts\IntegrationEventInterface;
use Ramsey\Uuid\Uuid;

class IntegrationEvent implements IntegrationEventInterface
{
    private readonly string $eventId;

    private readonly string $timestamp;

    private readonly array $metadata;

    public function __construct(
        private readonly string $eventType,
        private readonly string $version,
        private readonly string $source,
        private readonly int|string|null $tenantId,
        private readonly string $workspace,
        private readonly ?string $correlationId,
        private readonly array $actor,
        private readonly array $payload,
        ?string $eventId = null,
        ?string $timestamp = null,
        ?array $metadata = null,
    ) {
        $this->eventId = $eventId ?? Uuid::uuid4()->toString();
        $this->timestamp = $timestamp ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c');
        $this->metadata = $metadata ?? [
            'retry_count' => 0,
            'original_timestamp' => null,
        ];
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTenantId(): int|string|null
    {
        return $this->tenantId;
    }

    public function getWorkspace(): string
    {
        return $this->workspace;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getActor(): array
    {
        return $this->actor;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Returns a new instance with the retry count set to the given value.
     * Preserves the original eventId.
     */
    public function withRetryCount(int $count): self
    {
        $metadata = $this->metadata;
        $metadata['retry_count'] = $count;

        if ($metadata['original_timestamp'] === null) {
            $metadata['original_timestamp'] = $this->timestamp;
        }

        return new self(
            eventType: $this->eventType,
            version: $this->version,
            source: $this->source,
            tenantId: $this->tenantId,
            workspace: $this->workspace,
            correlationId: $this->correlationId,
            actor: $this->actor,
            payload: $this->payload,
            eventId: $this->eventId,
            timestamp: $this->timestamp,
            metadata: $metadata,
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'version' => $this->version,
            'source' => $this->source,
            'tenant_id' => $this->tenantId,
            'workspace' => $this->workspace,
            'correlation_id' => $this->correlationId,
            'actor' => $this->actor,
            'payload' => $this->payload,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
