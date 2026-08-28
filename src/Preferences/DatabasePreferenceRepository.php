<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Preferences;

use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Support\Carbon;

final class DatabasePreferenceRepository implements PreferenceRepository
{
    public function __construct(private Storage $storage, private ChannelRegistry $registry) {}

    private function key(RecipientIdentity $recipient, ?string $type, string $channel): string
    {
        if ($this->registry->get($channel)->definition->kind === ChannelKind::STRUCTURAL) {
            throw new ConfigurationException('Structural channels cannot have user preferences.');
        }

        return hash('sha256', json_encode([$recipient->type, $recipient->id, $type, $channel], JSON_THROW_ON_ERROR));
    }

    public function get(RecipientIdentity $recipient, ?string $type, string $channel): ?bool
    {
        $value = $this->storage->table('preferences')->where('id', $this->key($recipient, $type, $channel))->value('enabled');

        return $value === null ? null : (bool) $value;
    }

    public function set(RecipientIdentity $recipient, ?string $type, string $channel, bool $enabled): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $this->storage->table('preferences')->upsert([['id' => $this->key($recipient, $type, $channel),
            'notifiable_type' => $recipient->type, 'notifiable_id' => $recipient->id, 'notification_type' => $type,
            'channel' => $channel, 'enabled' => $enabled, 'created_at' => $now, 'updated_at' => $now]], ['id'], ['enabled', 'updated_at']);
    }

    public function delete(RecipientIdentity $recipient, ?string $type, string $channel): void
    {
        $this->storage->table('preferences')->where('id', $this->key($recipient, $type, $channel))->delete();
    }
}
