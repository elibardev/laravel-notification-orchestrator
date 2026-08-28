<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Contracts\FcmAccessTokenProvider;

final class FakeFcmToken implements FcmAccessTokenProvider
{
    public function validateConfiguration(): void {}

    public function token(): string
    {
        return 'test-oauth-token';
    }
}
