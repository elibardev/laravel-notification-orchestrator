<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Realtime;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Events\InboxChanged;
use Psr\Log\LoggerInterface;

final class BroadcastInboxChange
{
    public function __construct(private Configuration $config, private PersonalBroadcaster $broadcaster, private NotificationRepository $repository, private LoggerInterface $logger) {}

    public function handle(InboxChanged $change): void
    {
        if (! $this->config->enabled('broadcast')) {
            return;
        }
        try {
            $data = $change->data;
            $data['meta']['unread_count'] = $this->repository->unreadCount($change->recipient);
            if (isset($data['notification_id'])) {
                $stored = $this->repository->findFor($change->recipient, $data['notification_id']);
                if ($stored === null) {
                    return;
                }
                $data['state'] = $stored->state();
            }
            $this->broadcaster->send($change->event, $change->recipient, $data);
        } catch (\Throwable) {
            $this->logger->warning('notification.state_broadcast_failed', ['event' => $change->event]);
        }
    }
}
