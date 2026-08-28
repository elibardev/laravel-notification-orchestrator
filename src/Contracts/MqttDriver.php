<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;

interface MqttDriver
{
    public function validateConfiguration(): void;

    public function health(): ChannelHealth;

    public function publish(string $topic, string $payload, int $qos = 1, bool $retain = false): void;
}
