<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests;

use Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RuntimeException;

abstract class TestCase extends OrchestraTestCase
{
    protected string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = dirname(__DIR__).'/.cache/tests/'.bin2hex(random_bytes(8));
        (new Filesystem)->ensureDirectoryExists($this->fixturePath);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $root = realpath(dirname(__DIR__).'/.cache/tests');
            $path = realpath($this->fixturePath);

            if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Refusing to remove a test fixture outside the test cache.');
            }

            (new Filesystem)->deleteDirectory($path);
        }
    }

    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [NotificationOrchestratorServiceProvider::class];
    }
}
