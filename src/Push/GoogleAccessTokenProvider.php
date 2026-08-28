<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\FcmAccessTokenProvider;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Contracts\Cache\Repository;

final class GoogleAccessTokenProvider implements FcmAccessTokenProvider
{
    public function __construct(private Configuration $config, private Repository $cache, private GoogleAuthHttpHandler $http) {}

    /** @return array<string,mixed> */
    private function credentials(): array
    {
        $path = $this->config->get('push.drivers.fcm.credentials');
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new ChannelConfigurationException('FCM service-account credentials file is required.');
        }
        try {
            $data = json_decode(file_get_contents($path) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ChannelConfigurationException('FCM credentials are invalid.');
        }
        if (! is_array($data) || ! isset($data['client_email'],$data['private_key']) || ! is_string($data['client_email']) || ! is_string($data['private_key'])
            || ! filter_var($data['client_email'], FILTER_VALIDATE_EMAIL) || ! openssl_pkey_get_private($data['private_key'])) {
            throw new ChannelConfigurationException('FCM credentials are invalid.');
        }

        return $data;
    }

    public function validateConfiguration(): void
    {
        $this->credentials();
    }

    public function token(): string
    {
        try {
            $credentials = $this->credentials();
            $key = 'notification-orchestrator:fcm:'.hash('sha256', $credentials['client_email'].$credentials['private_key']);
            $cached = $this->cache->get($key);
            if (is_string($cached)) {
                return $cached;
            }
            $auth = new ServiceAccountCredentials('https://www.googleapis.com/auth/firebase.messaging', $credentials);
            $result = $auth->fetchAuthToken($this->http);
            if (! isset($result['access_token']) || ! is_string($result['access_token'])) {
                throw new DeliveryExecutionException;
            }
            $this->cache->put($key, $result['access_token'], max(1, ((int) ($result['expires_in'] ?? 3600)) - 60));

            return $result['access_token'];
        } catch (\Throwable) {
            throw new DeliveryExecutionException;
        }
    }
}
