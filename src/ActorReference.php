<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class ActorReference
{
    /** @var array<array-key, mixed> */
    public array $data;

    /** @param array<array-key, mixed> $data */
    public function __construct(public string $id, public ?string $type = null, public ?string $display = null, array $data = [])
    {
        Values::text($id, 'Actor id');
        $this->data = Values::data($data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'display' => $this->display, 'data' => (object) $this->data];
    }
}
