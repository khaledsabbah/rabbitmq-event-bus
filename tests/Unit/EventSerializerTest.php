<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Tests\Unit;

use Idaratech\EventBus\EventSerializer;
use Idaratech\EventBus\IntegrationEvent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EventSerializerTest extends TestCase
{
    private EventSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new EventSerializer();
    }

    private function createEvent(): IntegrationEvent
    {
        return new IntegrationEvent(
            eventType: 'employee.created',
            version: '1.0',
            source: 'backend-v2',
            tenantId: 42,
            workspace: 'staging_42',
            correlationId: 'corr-456',
            actor: ['user_id' => 1, 'type' => 'admin'],
            payload: ['name' => 'Jane Doe'],
        );
    }

    public function test_serialize_produces_valid_json(): void
    {
        $event = $this->createEvent();
        $json = $this->serializer->serialize($event);

        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'serialize() did not produce valid JSON');
        $this->assertSame('employee.created', $decoded['event_type']);
    }

    public function test_deserialize_reconstructs_event(): void
    {
        $original = $this->createEvent();
        $json = $this->serializer->serialize($original);
        $reconstructed = $this->serializer->deserialize($json);

        $this->assertSame($original->getEventId(), $reconstructed->getEventId());
        $this->assertSame($original->getEventType(), $reconstructed->getEventType());
        $this->assertSame($original->getVersion(), $reconstructed->getVersion());
        $this->assertSame($original->getSource(), $reconstructed->getSource());
        $this->assertSame($original->getTenantId(), $reconstructed->getTenantId());
        $this->assertSame($original->getWorkspace(), $reconstructed->getWorkspace());
        $this->assertSame($original->getCorrelationId(), $reconstructed->getCorrelationId());
        $this->assertSame($original->getActor(), $reconstructed->getActor());
        $this->assertSame($original->getPayload(), $reconstructed->getPayload());
        $this->assertSame($original->getTimestamp(), $reconstructed->getTimestamp());
        $this->assertSame($original->getMetadata(), $reconstructed->getMetadata());
    }

    public function test_deserialize_invalid_json_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->serializer->deserialize('not valid json {{{');
    }

    public function test_deserialize_missing_required_field_throws(): void
    {
        $json = json_encode([
            'event_id' => 'abc',
            'event_type' => 'test',
            // missing: version, source, tenant_id, workspace, actor, payload
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: version');
        $this->serializer->deserialize($json);
    }
}
