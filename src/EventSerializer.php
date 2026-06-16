<?php

declare(strict_types=1);

namespace Idaratech\EventBus;

use Idaratech\EventBus\Contracts\IntegrationEventInterface;
use InvalidArgumentException;

class EventSerializer
{
    private const REQUIRED_FIELDS = [
        'event_id',
        'event_type',
        'version',
        'source',
        'tenant_id',
        'workspace',
        'actor',
        'payload',
    ];

    public function serialize(IntegrationEventInterface $event): string
    {
        return $event->toJson();
    }

    public function deserialize(string $json): IntegrationEvent
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                'Invalid JSON: ' . json_last_error_msg(),
            );
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    "Missing required field: {$field}",
                );
            }
        }

        return new IntegrationEvent(
            eventType: $data['event_type'],
            version: $data['version'],
            source: $data['source'],
            tenantId: $data['tenant_id'],
            workspace: $data['workspace'],
            correlationId: $data['correlation_id'] ?? null,
            actor: $data['actor'],
            payload: $data['payload'],
            eventId: $data['event_id'],
            timestamp: $data['timestamp'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
