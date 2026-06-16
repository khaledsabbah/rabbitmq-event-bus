<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Consumer;

class ConsumerConfig
{
    public function __construct(
        public readonly string $queue,
        public readonly string $retryExchange,
        public readonly string $dlqRoutingKey,
        public readonly int $maxRetries = 3,
        public readonly array $retryDelays = [1000, 5000, 25000],
        public readonly int $prefetchCount = 1,
    ) {}
}
