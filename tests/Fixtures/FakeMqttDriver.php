<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;

final class FakeMqttDriver implements MqttDriver
{
    /** @var list<array{topic:string,payload:string,qos:int,retain:bool}> */
    public array $sent = [];

    public bool $fail = false;

    public function validateConfiguration(): void {}

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function publish(string $topic, string $payload, int $qos = 1, bool $retain = false): void
    {
        if ($this->fail) {
            throw new \RuntimeException('SECRET-MQTT-PASSWORD');
        }
        $this->sent[] = compact('topic', 'payload', 'qos', 'retain');
    }
}
