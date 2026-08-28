<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

final readonly class DeliveryResult
{
    public function __construct(public string $channel, public DeliveryStatus $status, public ?string $provider = null,
        public ?string $providerReference = null, public ?string $errorCode = null) {}
}
