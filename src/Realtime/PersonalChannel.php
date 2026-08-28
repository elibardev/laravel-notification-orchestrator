<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Realtime;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final class PersonalChannel
{
    public function __construct(private Configuration $config) {}

    public function name(RecipientIdentity $owner): string
    {
        $pattern = $this->config->get('broadcast.personal_channel');
        if (! is_string($pattern) || ! str_contains($pattern, '{notifiable}') || ! str_contains($pattern, '{id}') || str_contains($owner->type, '\\')) {
            throw new ChannelConfigurationException('Personal broadcasting requires a morph alias and an owner-scoped channel pattern.');
        }

        return strtr($pattern, ['{notifiable}' => rawurlencode($owner->type), '{id}' => rawurlencode($owner->id)]);
    }
}
