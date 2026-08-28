<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Recipients;

use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidRecipientException;
use Illuminate\Database\Eloquent\Model;

final class DefaultRecipientNormalizer implements RecipientNormalizer
{
    public function normalize(object $recipient): RecipientIdentity
    {
        if ($recipient instanceof RecipientIdentity) {
            return $recipient;
        }
        if ($recipient instanceof Model && $recipient->getKey() !== null) {
            return new RecipientIdentity($recipient->getMorphClass(), (string) $recipient->getKey());
        }
        throw new InvalidRecipientException('Provide a keyed Eloquent model, RecipientIdentity, or custom RecipientNormalizer.');
    }
}
