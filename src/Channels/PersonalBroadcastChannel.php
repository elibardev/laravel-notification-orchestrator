<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Realtime\PersonalBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository;

final class PersonalBroadcastChannel implements NotificationChannel
{
    public function __construct(private PersonalBroadcaster $broadcaster, private NotificationRepository $repository, private Configuration $config, private Repository $application, private BroadcastManager $manager) {}

    public function name(): string
    {
        return 'broadcast';
    }

    public function validateConfiguration(): void
    {
        $connection = $this->config->get('broadcast.connection') ?? $this->application->get('broadcasting.default');
        $driver = $this->application->get('broadcasting.connections.'.$connection.'.driver');
        if (! is_string($driver) || $driver === 'null') {
            throw new ChannelConfigurationException('A broadcasting connection is required.');
        }
        try {
            $this->manager->connection($connection);
        } catch (\Throwable) {
            throw new ChannelConfigurationException('The broadcasting connection configuration is invalid.');
        }
    }

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        $plan = $delivery->recipientPlan;
        $stored = $plan->storedNotificationId === null ? null : $this->repository->findFor($plan->recipient, $plan->storedNotificationId);
        if ($plan->storedNotificationId !== null && $stored === null) {
            throw new \LogicException('Inbox must be persisted before broadcast.');
        }
        $this->broadcaster->send('notification.created', $plan->recipient, ['notification' => $stored?->toArray() ?? $plan->payload->toArray(),
            'meta' => ['unread_count' => $stored === null ? null : $this->repository->unreadCount($plan->recipient)]], $delivery->channelPlan->destinations[0]->value);

        return new DeliveryResult('broadcast', DeliveryStatus::SENT, 'laravel');
    }
}
