<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

interface PreferenceRepository
{
    public function get(RecipientIdentity $recipient, ?string $type, string $channel): ?bool;

    public function set(RecipientIdentity $recipient, ?string $type, string $channel, bool $enabled): void;

    public function delete(RecipientIdentity $recipient, ?string $type, string $channel): void;
}
