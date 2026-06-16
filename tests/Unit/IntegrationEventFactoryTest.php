<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Tests\Unit;

use Idaratech\EventBus\Contracts\ContextProviderInterface;
use Idaratech\EventBus\IntegrationEventFactory;
use PHPUnit\Framework\TestCase;

class IntegrationEventFactoryTest extends TestCase
{
    public function test_make_builds_event_from_context_provider(): void
    {
        $contextProvider = new class implements ContextProviderInterface {
            public function getTenantId(): int|string
            {
                return 42;
            }

            public function getWorkspace(): string
            {
                return 'staging_42';
            }

            public function getCorrelationId(): ?string
            {
                return 'req-abc-123';
            }

            public function getActor(): array
            {
                return ['user_id' => 7, 'type' => 'employee'];
            }
        };

        $factory = new IntegrationEventFactory($contextProvider, 'backend-v2');

        $event = $factory->make('employee.created', '1.0', ['employee_id' => 99]);

        $this->assertSame('employee.created', $event->getEventType());
        $this->assertSame('1.0', $event->getVersion());
        $this->assertSame('backend-v2', $event->getSource());
        $this->assertSame(42, $event->getTenantId());
        $this->assertSame('staging_42', $event->getWorkspace());
        $this->assertSame('req-abc-123', $event->getCorrelationId());
        $this->assertSame(['user_id' => 7, 'type' => 'employee'], $event->getActor());
        $this->assertSame(['employee_id' => 99], $event->getPayload());
        $this->assertNotEmpty($event->getEventId());
        $this->assertNotEmpty($event->getTimestamp());
    }

    public function test_make_handles_null_correlation_id(): void
    {
        $contextProvider = new class implements ContextProviderInterface {
            public function getTenantId(): int|string
            {
                return 1;
            }

            public function getWorkspace(): string
            {
                return 'staging_1';
            }

            public function getCorrelationId(): ?string
            {
                return null;
            }

            public function getActor(): array
            {
                return ['user_id' => 0, 'type' => 'system'];
            }
        };

        $factory = new IntegrationEventFactory($contextProvider, 'admin-panel');

        $event = $factory->make('company.settings.updated', '1.0', []);

        $this->assertNull($event->getCorrelationId());
        $this->assertSame('admin-panel', $event->getSource());
    }
}
