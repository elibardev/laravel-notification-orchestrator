<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Http\Request;

interface AuthenticatedNotifiableResolver
{
    public function resolve(Request $request): RecipientIdentity;
}
