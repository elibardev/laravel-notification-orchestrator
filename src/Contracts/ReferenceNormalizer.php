<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\ActorReference;
use Elibardev\NotificationOrchestrator\SubjectReference;

interface ReferenceNormalizer
{
    public function actor(object|string|int|null $value): ?ActorReference;

    public function subject(object|string|int|null $value): ?SubjectReference;
}
