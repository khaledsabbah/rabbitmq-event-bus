<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Tests\Unit;

use Idaratech\EventBus\IntegrationEvent;
use PHPUnit\Framework\TestCase;

class IntegrationEventTest extends TestCase
{
    private function createEvent(array $overrides = []): IntegrationEvent
    {
        return new IntegrationEvent(
            eventType: $overrides['eventType'] ?? 'employee.created',
            version: $overrides['version'] ?? '1.0',
            source: $overrides['source'] ?? 'backend-v2',
            tenantId: $overrides['tenantId'] ?? 42,
            workspace: $overrides['workspace'] ?? 'staging_42',
            correlationId: $overrides['correlationId'] ?? 'corr-123',
            actor: $overrides['actor'] ?? ['user_id' => 1, 'type' => 'admin'],
            payload: $overrides['payload'] ?? ['name' => 'John Doe', 'email' => 'john@example.com'],
        );
    }

    public function test_creates_event_with_auto_generated_fields(): void
    {
        $event = $this->createEvent();

        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $event->getEventId(),
        );

        // Timestamp is not empty and is ISO 8601
        $this->assertNotEmpty($event->getTimestamp());
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $event->getTimestamp(),
        );

        // All fields match
        $this->assertSame('employee.created', $event->getEventType());
        $this->assertSame('1.0', $event->getVersion());
        $this->assertSame('backend-v2', $event->getSource());
        $this->assertSame(42, $event->getTenantId());
        $this->assertSame('staging_42', $event->getWorkspace());
        $this->assertSame('corr-123', $event->getCorrelationId());
        $this->assertSame(['user_id' => 1, 'type' => 'admin'], $event->getActor());
        $this->assertSame(['name' => 'John Doe', 'email' => 'john@example.com'], $event->getPayload());
    }

    public function test_to_array_contains_all_envelope_fields(): void
    {
        $event = $this->createEvent();
        $array = $event->toArray();

        $expectedKeys = [
            'event_id',
            'event_type',
            'version',
            'source',
            'tenant_id',
            'workspace',
            'correlation_id',
            'actor',
            'payload',
            'timestamp',
            'metadata',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, "Missing key: {$key}");
        }

        $this->assertCount(11, $array);
    }

    public function test_to_json_produces_valid_json(): void
    {
        $event = $this->createEvent();
        $json = $event->toJson();

        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'toJson() did not produce valid JSON');
        $this->assertSame($event->getEventId(), $decoded['event_id']);
        $this->assertSame('employee.created', $decoded['event_type']);
        $this->assertSame('1.0', $decoded['version']);
    }

    public function test_with_retry_count_increments_metadata(): void
    {
        $event = $this->createEvent();
        $originalId = $event->getEventId();

        $this->assertSame(0, $event->getMetadata()['retry_count']);

        $retried = $event->withRetryCount(1);

        // Immutable: original unchanged
        $this->assertSame(0, $event->getMetadata()['retry_count']);

        // New instance has incremented retry
        $this->assertSame(1, $retried->getMetadata()['retry_count']);

        // Preserves eventId
        $this->assertSame($originalId, $retried->getEventId());

        // Different instance
        $this->assertNotSame($event, $retried);
    }
}
