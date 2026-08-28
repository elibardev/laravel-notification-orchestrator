<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestNotifiable extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public function getMorphClass(): string
    {
        return 'test-user';
    }
}
