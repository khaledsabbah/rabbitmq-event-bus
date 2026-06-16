<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Tests\Unit;

use Idaratech\EventBus\IntegrationEvent;
use Idaratech\EventBus\NullEventBus;
use PHPUnit\Framework\TestCase;

class NullEventBusTest extends TestCase
{
    public function test_publish_does_not_throw(): void
    {
        $bus = new NullEventBus();

        $event = new IntegrationEvent(
            eventType: 'employee.created',
            version: '1.0',
            source: 'backend-v2',
            tenantId: 1,
            workspace: 'staging_1',
            correlationId: null,
            actor: ['user_id' => 1, 'type' => 'system'],
            payload: ['id' => 100],
        );

        $bus->publish('hr.events', $event);

        // No exception means success
        $this->assertTrue(true);
    }

    public function test_close_does_not_throw(): void
    {
        $bus = new NullEventBus();
        $bus->close();

        $this->assertTrue(true);
    }

    public function test_get_published_events_returns_events(): void
    {
        $bus = new NullEventBus();

        $event = new IntegrationEvent(
            eventType: 'shift.assigned',
            version: '1.0',
            source: 'backend-v2',
            tenantId: 5,
            workspace: 'staging_5',
            correlationId: 'corr-789',
            actor: ['user_id' => 2, 'type' => 'manager'],
            payload: ['shift_id' => 42],
        );

        $bus->publish('attendance.events', $event);
        $bus->publish('hr.events', $event);

        $published = $bus->getPublishedEvents();

        $this->assertCount(2, $published);
        $this->assertSame('attendance.events', $published[0]['exchange']);
        $this->assertSame('hr.events', $published[1]['exchange']);
        $this->assertSame('shift.assigned', $published[0]['event']->getEventType());
        $this->assertSame('shift.assigned', $published[1]['event']->getEventType());
    }
}
