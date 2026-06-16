<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Contracts;

interface ContextProviderInterface
{
    public function getTenantId(): int|string;

    public function getWorkspace(): string;

    public function getCorrelationId(): ?string;

    /**
     * @return array{user_id: int, type: string}
     */
    public function getActor(): array;
}
