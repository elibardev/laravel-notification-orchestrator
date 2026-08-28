<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Realtime;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Carbon;

final class PersonalBroadcaster
{
    public function __construct(private BroadcastManager $broadcasts, private Configuration $config, private PersonalChannel $channels) {}

    /** @param array<string,mixed> $data */
    public function send(string $event, RecipientIdentity $owner, array $data, ?string $destination = null): void
    {
        $this->broadcasts->connection($this->config->get('broadcast.connection'))->broadcast([new PrivateChannel($destination ?? $this->channels->name($owner))],
            $event, ['schema' => '1.0', 'event' => $event, 'occurred_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s.u\Z')] + $data);
    }
}
