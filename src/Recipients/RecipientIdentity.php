<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Recipients;

use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class RecipientIdentity
{
    public function __construct(public string $type, public string $id)
    {
        Values::text($type, 'Recipient type');
        Values::text($id, 'Recipient id');
    }

    public function key(): string
    {
        return json_encode([$this->type, $this->id], JSON_THROW_ON_ERROR);
    }
}
