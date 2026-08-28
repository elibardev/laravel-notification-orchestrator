<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;

final class AuthenticatedAccount extends User
{
    use Notifiable;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public function getMorphClass(): string
    {
        return 'account';
    }
}
