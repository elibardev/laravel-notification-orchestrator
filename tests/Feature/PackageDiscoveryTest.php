<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\ServiceProvider;

class PackageDiscoveryTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        // Do not register the provider manually: Laravel must discover it.
        return [];
    }

    /** @param Application $app */
    protected function resolveApplicationResolvingCallback($app): void
    {
        parent::resolveApplicationResolvingCallback($app);

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->fixturePath.'/vendor/composer');
        $files->ensureDirectoryExists($this->fixturePath.'/bootstrap/cache');
        $package = json_decode($files->get(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        // A consuming application's installed metadata, using the real package manifest.
        $files->put(
            $this->fixturePath.'/vendor/composer/installed.json',
            json_encode(['packages' => [$package]], JSON_THROW_ON_ERROR),
        );

        $manifest = new PackageManifest($files, $this->fixturePath, $this->fixturePath.'/bootstrap/cache/packages.php');
        $manifest->vendorPath = $this->fixturePath.'/vendor';
        $app->instance(PackageManifest::class, $manifest);
    }

    public function test_laravel_discovers_and_boots_the_provider_from_composer_metadata(): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        self::assertInstanceOf(
            NotificationOrchestratorServiceProvider::class,
            $app->getProvider(NotificationOrchestratorServiceProvider::class),
        );
        self::assertTrue(config('notification-orchestrator.features.database'));
        self::assertContains(
            $app->configPath('notification-orchestrator.php'),
            ServiceProvider::pathsToPublish(NotificationOrchestratorServiceProvider::class, 'notification-orchestrator-config'),
        );
    }
}
