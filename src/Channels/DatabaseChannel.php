<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Persistence\Storage;

final class DatabaseChannel implements NotificationChannel
{
    public function __construct(private NotificationRepository $repository, private Storage $storage) {}

    public function name(): string
    {
        return 'database';
    }

    public function validateConfiguration(): void {}

    public function health(): ChannelHealth
    {
        return new ChannelHealth($this->storage->available('notifications') ? HealthStatus::HEALTHY : HealthStatus::INVALID);
    }

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        $this->repository->store($delivery->recipientPlan);

        return new DeliveryResult($this->name(), DeliveryStatus::SENT, 'database');
    }
}
