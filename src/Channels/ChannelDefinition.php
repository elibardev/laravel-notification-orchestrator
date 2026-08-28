<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Exceptions\ChannelRegistrationException;
use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class ChannelDefinition
{
    public function __construct(public string $name, public ChannelKind $kind, public string $driver,
        public bool $preferenceAware, public bool $requiresDestination, public bool $queueable, public bool $healthCheckable)
    {
        Values::name($name);
        Values::text($driver, 'Driver');
        if ($kind === ChannelKind::STRUCTURAL && $preferenceAware) {
            throw new ChannelRegistrationException('Structural channels cannot use preferences.');
        }
    }
}
