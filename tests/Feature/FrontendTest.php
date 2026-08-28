<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;

class FrontendTest extends TestCase
{
    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('notification-orchestrator.features.blade', true);
    }

    public function test_blade_components_render_once_without_framework_dependencies(): void
    {
        $html = Blade::render('<x-notifications::bell /><x-notifications::inbox /><x-notifications::toast-container />');
        self::assertStringContainsString('data-notifications="bell"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertSame(1, substr_count($html, 'src="http://localhost/vendor/notification-orchestrator/js/blade-adapter.js"'));
        self::assertStringNotContainsString('Alpine', $html);
        config(['notification-orchestrator.blade.styles' => false]);
        self::assertStringNotContainsString('rel="stylesheet"', Blade::render('<x-notifications::bell />'));
    }
}
