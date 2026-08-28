<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Push\PushDestination;
use Elibardev\NotificationOrchestrator\Push\PushMessage;
use Elibardev\NotificationOrchestrator\Push\PushResult;

interface PushDriver
{
    public function send(PushDestination $destination, PushMessage $message): PushResult;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;
}
