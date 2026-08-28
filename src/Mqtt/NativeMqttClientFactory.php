<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mqtt;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use PhpMqtt\Client\Contracts\MqttClient as ClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\MqttClient;

final class NativeMqttClientFactory implements MqttClientFactory
{
    public function __construct(private Configuration $config) {}

    public function make(Repository $messages): ClientContract
    {
        // The library's default null logger avoids exposing topics/payloads.
        return new MqttClient($this->config->get('mqtt.host'), $this->config->get('mqtt.port'), null, MqttClient::MQTT_3_1_1, $messages);
    }
}
