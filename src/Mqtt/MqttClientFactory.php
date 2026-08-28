<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mqtt;

use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Contracts\Repository;

interface MqttClientFactory
{
    public function make(Repository $messages): MqttClient;
}
