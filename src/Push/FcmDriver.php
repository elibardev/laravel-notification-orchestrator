<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\FcmAccessTokenProvider;
use Elibardev\NotificationOrchestrator\Contracts\PushDriver;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Illuminate\Http\Client\Factory;

final class FcmDriver implements PushDriver
{
    public function __construct(private Factory $http, private Configuration $config, private FcmAccessTokenProvider $tokens) {}

    public function validateConfiguration(): void
    {
        $project = $this->config->get('push.drivers.fcm.project_id');
        if (! is_string($project) || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $project)) {
            throw new ChannelConfigurationException('FCM project_id is required.');
        }
        $this->tokens->validateConfiguration();
    }

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function send(PushDestination $destination, PushMessage $message): PushResult
    {
        $body = ['message' => ['token' => $destination->token, 'notification' => ['title' => $message->payload->title, 'body' => $message->payload->message], 'data' => $message->data()]];
        if (strlen(json_encode($body, JSON_THROW_ON_ERROR)) > 4096) {
            return new PushResult(false);
        }
        try {
            $response = $this->http->withToken($this->tokens->token())->timeout(15)->post(
                'https://fcm.googleapis.com/v1/projects/'.$this->config->get('push.drivers.fcm.project_id').'/messages:send', $body);
            if ($response->successful() && is_string($response->json('name'))) {
                return new PushResult(true, providerReference: $response->json('name'));
            }
            $invalid = false;
            foreach ((array) $response->json('error.details', []) as $detail) {
                if (is_array($detail) && ($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError' && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                    $invalid = true;
                }
            }

            return new PushResult(false, $invalid);
        } catch (\Throwable) {
            throw new DeliveryExecutionException;
        }
    }
}
