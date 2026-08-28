<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

final readonly class PushResult
{
    public function __construct(public bool $accepted, public bool $invalidDestination = false, public ?string $providerReference = null) {}
}
