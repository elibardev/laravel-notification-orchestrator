<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mail;

use Elibardev\NotificationOrchestrator\NotificationContext;
use Illuminate\Notifications\Notification;

final class MailRoutingNotification extends Notification
{
    public function __construct(public readonly NotificationContext $context) {}
}
