<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

interface RecipientNormalizer
{
    public function normalize(object $recipient): RecipientIdentity;
}
