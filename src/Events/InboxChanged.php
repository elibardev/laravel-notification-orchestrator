<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Events;

use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final readonly class InboxChanged
{
    /** @param array<string,mixed> $data */
    public function __construct(public string $event, public RecipientIdentity $recipient, public array $data) {}
}
