<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Http;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Realtime\PersonalChannel;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;

final class BroadcastAuthController
{
    public function __invoke(Request $request, AuthenticatedNotifiableResolver $owners, PersonalChannel $channels, BroadcastManager $broadcasts, Configuration $config): mixed
    {
        $data = $request->validate(['channel_name' => 'required|string', 'socket_id' => 'required|string']);
        abort_unless(hash_equals('private-'.$channels->name($owners->resolve($request)), $data['channel_name']), 403);

        return $broadcasts->connection($config->get('broadcast.connection'))->auth($request);
    }
}
