<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Channel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RecordingBroadcaster extends Broadcaster
{
    /** @var list<array<string,mixed>> */
    public array $messages = [];

    public bool $fail = false;

    public function auth($request)
    {
        if (! $request->user()) {
            throw new AccessDeniedHttpException;
        }

        return $this->verifyUserCanAccessChannel($request, preg_replace('/^private-/', '', $request->input('channel_name')));
    }

    public function validAuthenticationResponse($request, $result)
    {
        return ['authorized' => (bool) $result];
    }

    /** @param array<Channel|string> $channels
     * @param array<string,mixed> $payload */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        if ($this->fail) {
            throw new \RuntimeException('SECRET-BROADCAST-KEY');
        }
        $this->messages[] = ['channels' => array_map(strval(...), $channels), 'event' => $event, 'payload' => $payload];
    }
}
