<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mqtt;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\NotificationContext;

final class MqttDestinationResolver implements ChannelDestinationResolver
{
    public function __construct(private RecipientNormalizer $normalizer, private Configuration $config) {}

    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        $owner = $this->normalizer->normalize($recipient);
        $pattern = $this->config->get('mqtt.personal_topic');
        if (! is_string($pattern) || ! str_contains($pattern, '{id}') || ! str_contains($pattern, '{notifiable}') || str_contains($owner->type, '\\')) {
            throw new ChannelConfigurationException('Personal MQTT requires an owner-scoped pattern and morph alias.');
        }
        $topic = strtr($pattern, ['{id}' => rawurlencode($owner->id), '{notifiable}' => rawurlencode($owner->type)]);
        ContextTarget::mqtt($topic);

        return [new ChannelDestination($topic)];
    }
}
