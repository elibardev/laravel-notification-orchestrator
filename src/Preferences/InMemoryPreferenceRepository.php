<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Preferences;

use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final class InMemoryPreferenceRepository implements PreferenceRepository
{
    /** @var array<string,bool> */
    private array $values = [];

    public function __construct(private ChannelRegistry $registry) {}

    private function key(RecipientIdentity $recipient, ?string $type, string $channel): string
    {
        if ($this->registry->get($channel)->definition->kind === ChannelKind::STRUCTURAL) {
            throw new ConfigurationException('Structural channels cannot have user preferences.');
        }

        return json_encode([$recipient->key(), $type, $channel], JSON_THROW_ON_ERROR);
    }

    public function get(RecipientIdentity $recipient, ?string $type, string $channel): ?bool
    {
        return $this->values[$this->key($recipient, $type, $channel)] ?? null;
    }

    public function set(RecipientIdentity $recipient, ?string $type, string $channel, bool $enabled): void
    {
        $this->values[$this->key($recipient, $type, $channel)] = $enabled;
    }

    public function delete(RecipientIdentity $recipient, ?string $type, string $channel): void
    {
        unset($this->values[$this->key($recipient, $type, $channel)]);
    }
}
