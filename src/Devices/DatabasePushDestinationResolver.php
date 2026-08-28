<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Devices;

use Elibardev\NotificationOrchestrator\Contracts\PushDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\NotificationContext;

final class DatabasePushDestinationResolver implements PushDestinationResolver
{
    public function __construct(private DeviceRepository $devices, private RecipientNormalizer $normalizer) {}

    public function resolve(object $notifiable, NotificationContext $context): iterable
    {
        return $this->devices->destinations($this->normalizer->normalize($notifiable));
    }
}
