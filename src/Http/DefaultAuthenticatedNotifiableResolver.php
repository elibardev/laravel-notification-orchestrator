<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Http;

use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class DefaultAuthenticatedNotifiableResolver implements AuthenticatedNotifiableResolver
{
    public function __construct(private RecipientNormalizer $normalizer) {}

    public function resolve(Request $request): RecipientIdentity
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return $this->normalizer->normalize($user);
    }
}
