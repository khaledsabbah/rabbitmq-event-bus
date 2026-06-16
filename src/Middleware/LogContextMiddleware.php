<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Middleware;

use Idaratech\EventBus\Contracts\IntegrationEventInterface;
use Illuminate\Support\Facades\Context;

class LogContextMiddleware
{
    public static function before(IntegrationEventInterface $event): void
    {
        Context::flush();

        Context::add('event_id', $event->getEventId());
        Context::add('event_type', $event->getEventType());
        Context::add('correlation_id', $event->getCorrelationId());
        Context::add('tenant_id', $event->getTenantId());
        Context::add('workspace', $event->getWorkspace());
    }

    public static function after(): void
    {
        Context::flush();
    }
}
