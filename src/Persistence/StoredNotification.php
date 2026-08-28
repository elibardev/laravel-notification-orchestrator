<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

final readonly class StoredNotification
{
    /** @param array<string,mixed> $payload */
    public function __construct(public string $id, public array $payload, public ?string $readAt, public string $createdAt) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return array_replace($this->payload, ['id' => $this->id, 'state' => $this->state(), 'created_at' => $this->createdAt]);
    }

    /** @return array{read:bool,read_at:?string} */
    public function state(): array
    {
        return ['read' => $this->readAt !== null, 'read_at' => $this->readAt];
    }
}
