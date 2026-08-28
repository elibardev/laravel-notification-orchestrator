<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class PushDestination
{
    /** @var array<string,mixed> */
    public array $metadata;

    /** @param array<string,mixed> $metadata */
    public function __construct(#[\SensitiveParameter] public string $token, public string $driver, public ?string $platform = null,
        public ?string $deviceId = null, public ?string $label = null, array $metadata = [])
    {
        Values::text($token, 'Push token');
        Values::name($driver);
        $this->metadata = Values::data($metadata);
    }
}
