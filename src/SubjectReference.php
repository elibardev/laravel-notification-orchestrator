<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class SubjectReference
{
    /** @var array<array-key, mixed> */
    public array $data;

    /** @param array<array-key, mixed> $data */
    public function __construct(public string $type, public string $id, array $data = [])
    {
        Values::text($type, 'Subject type');
        Values::text($id, 'Subject id');
        $this->data = Values::data($data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id, 'data' => (object) $this->data];
    }
}
