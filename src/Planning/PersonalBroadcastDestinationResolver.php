<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Planning;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\NotificationContext;

final class PersonalBroadcastDestinationResolver implements ChannelDestinationResolver
{
    public function __construct(private RecipientNormalizer $normalizer, private Configuration $config) {}

    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        $identity = $this->normalizer->normalize($recipient);
        if (str_contains($identity->type, '\\')) {
            throw new ChannelConfigurationException('Personal broadcasting requires a morph alias or custom destination resolver.');
        }
        $pattern = $this->config->get('broadcast.personal_channel');
        if (! is_string($pattern) || ! str_contains($pattern, '{id}') || ! str_contains($pattern, '{notifiable}')) {
            throw new ChannelConfigurationException('broadcast.personal_channel must identify both notifiable type and id.');
        }

        return [new ChannelDestination(strtr($pattern, ['{id}' => rawurlencode($identity->id), '{notifiable}' => rawurlencode($identity->type)]))];
    }
}
