<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Contracts\PushDriver;
use Elibardev\NotificationOrchestrator\Push\PushDestination;
use Elibardev\NotificationOrchestrator\Push\PushMessage;
use Elibardev\NotificationOrchestrator\Push\PushResult;

final class FakePushDriver implements PushDriver
{
    /** @var list<PushMessage> */
    public array $messages = [];

    public bool $invalid = false;

    public function validateConfiguration(): void {}

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function send(PushDestination $destination, PushMessage $message): PushResult
    {
        $this->messages[] = $message;

        return new PushResult(! $this->invalid, $this->invalid, 'fake-reference');
    }
}
