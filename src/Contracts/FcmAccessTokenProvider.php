<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

interface FcmAccessTokenProvider
{
    public function validateConfiguration(): void;

    public function token(): string;
}
