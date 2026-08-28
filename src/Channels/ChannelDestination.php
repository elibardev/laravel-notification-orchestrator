<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class ChannelDestination
{
    /** @var array<array-key,mixed> */
    public array $metadata;

    /** @param array<array-key,mixed> $metadata */
    public function __construct(#[\SensitiveParameter] public string $value, array $metadata = [])
    {
        Values::text($value, 'Destination');
        $this->metadata = Values::data($metadata);
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->value);
    }
}
