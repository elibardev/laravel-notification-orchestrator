<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider;
use Elibardev\NotificationOrchestrator\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_laravel_boots_with_the_package_provider(): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        self::assertStringStartsWith('12.', $app->version());
        self::assertTrue($app->isBooted());
        self::assertInstanceOf(
            NotificationOrchestratorServiceProvider::class,
            $app->getProvider(NotificationOrchestratorServiceProvider::class),
        );
    }

    public function test_canonical_configuration_is_loaded_without_optional_providers(): void
    {
        self::assertTrue(config('notification-orchestrator.features.database'));
        self::assertFalse(config('notification-orchestrator.features.push'));
        self::assertFalse(config('notification-orchestrator.features.blade'));
        self::assertNull(config('notification-orchestrator.database.connection'));
        self::assertSame('notify_', config('notification-orchestrator.database.table_prefix'));
        self::assertSame('notifications', config('notification-orchestrator.queue.queue'));
    }
}
