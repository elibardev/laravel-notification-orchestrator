<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tracking;

use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;

final class DeliveryTransitionGuard
{
    public function assertAllowed(DeliveryStatus $from, DeliveryStatus $to): void
    {
        if ($from === $to) {
            return;
        }
        $allowed = match ($from) {
            DeliveryStatus::PLANNED => ['queued', 'processing', 'skipped', 'failed'],
            DeliveryStatus::QUEUED => ['processing', 'failed'],
            DeliveryStatus::PROCESSING => ['sent', 'failed'],
            DeliveryStatus::FAILED => ['queued', 'processing'],
            DeliveryStatus::SENT => ['delivered', 'failed'],
            default => [],
        };
        if (! in_array($to->value, $allowed, true)) {
            throw new \LogicException('Invalid delivery state transition.');
        }
    }
}
