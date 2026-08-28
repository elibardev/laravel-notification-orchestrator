<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mqtt;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Repositories\MemoryRepository;

final class PhpMqttDriver implements MqttDriver
{
    public function __construct(private MqttClientFactory $factory, private Configuration $config) {}

    public function validateConfiguration(): void
    {
        $host = $this->config->get('mqtt.host');
        $port = $this->config->get('mqtt.port');
        $timeout = $this->config->get('mqtt.timeout');
        if (! is_string($host) || trim($host) === '' || preg_match('/[\s\/]/', $host) || ! is_int($port) || $port < 1 || $port > 65535
            || ! is_int($timeout) || $timeout < 1 || $timeout > 60 || ! is_bool($this->config->get('mqtt.tls'))) {
            throw new ChannelConfigurationException('Invalid MQTT connection configuration.');
        }
        foreach (['mqtt.username', 'mqtt.password'] as $key) {
            if ($this->config->get($key) !== null && ! is_string($this->config->get($key))) {
                throw new ChannelConfigurationException('Invalid MQTT authentication configuration.');
            }
        }
    }

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function publish(string $topic, string $payload, int $qos = 1, bool $retain = false): void
    {
        ContextTarget::mqtt($topic, $qos, $retain);
        $this->validateConfiguration();
        $messages = new MemoryRepository;
        $client = $this->factory->make($messages);
        $settings = (new ConnectionSettings)->setUsername($this->config->get('mqtt.username'))->setPassword($this->config->get('mqtt.password'))
            ->setConnectTimeout($this->config->get('mqtt.timeout'))->setSocketTimeout($this->config->get('mqtt.timeout'))
            ->setUseTls($this->config->get('mqtt.tls'))->setTlsVerifyPeer(true)->setTlsVerifyPeerName(true);
        try {
            $client->connect($settings, true);
            $client->publish($topic, $payload, $qos, $retain);
            if ($qos === 1) {
                $client->loop(true, true, $this->config->get('mqtt.timeout'));
                if ($messages->countPendingOutgoingMessages() > 0) {
                    throw new DeliveryExecutionException;
                }
            }
        } catch (\Throwable) {
            throw new DeliveryExecutionException;
        } finally {
            try {
                if ($client->isConnected()) {
                    $client->disconnect();
                }
            } catch (\Throwable) {
            }
        }
    }
}
