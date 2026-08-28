<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Configuration\CapabilityRegistry;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Configuration\ConfigurationMerger;
use Elibardev\NotificationOrchestrator\Configuration\TableNameResolver;
use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Testing\PendingCommand;

class ConfigurationTest extends TestCase
{
    public function test_schema_merge_preserves_maps_and_replaces_lists_including_empty_lists(): void
    {
        $merge = new ConfigurationMerger;
        $defaults = ['features' => ['database' => true, 'push' => false], 'api' => ['middleware' => ['api', 'auth']],
            'channels' => ['types' => ['item.created' => ['mail', 'push']]], 'database' => ['connection' => 'default']];
        $actual = $merge->merge($defaults, ['features' => ['database' => false], 'api' => ['middleware' => []],
            'channels' => ['types' => ['item.created' => ['mail']]], 'database' => ['connection' => null]]);
        self::assertSame(['database' => false, 'push' => false], $actual['features']);
        self::assertSame([], $actual['api']['middleware']);
        self::assertSame(['mail'], $actual['channels']['types']['item.created']);
        self::assertNull($actual['database']['connection']);
        self::assertSame($defaults, $merge->merge($defaults, ['features' => []]));
    }

    public function test_table_names_are_centralized_and_explicit_override_wins(): void
    {
        config(['notification-orchestrator.database.table_prefix' => 'app_',
            'notification-orchestrator.database.tables.preferences' => 'custom_preferences']);
        $names = new TableNameResolver(new Configuration(app(Repository::class)));
        self::assertSame('app_notifications', $names->for('notifications'));
        self::assertSame('custom_preferences', $names->for('preferences'));
        $this->expectException(ConfigurationException::class);
        $names->for('dispatches');
    }

    public function test_duplicate_switch_diagnostics_do_not_echo_values(): void
    {
        config(['notification-orchestrator.push.enabled' => 'SECRET-EXAMPLE']);
        $configuration = new Configuration(app(Repository::class));
        self::assertStringContainsString('features.push', implode(' ', $configuration->errors()));
        self::assertStringNotContainsString('SECRET-EXAMPLE', implode(' ', $configuration->errors()));
        $this->expectException(ConfigurationException::class);
        $configuration->validate();
    }

    public function test_missing_capability_dependency_is_not_silently_activated(): void
    {
        config(['notification-orchestrator.features' => ['custom' => true, 'dependency' => false]]);
        $capabilities = new CapabilityRegistry(new Configuration(app(Repository::class)));
        $capabilities->register('custom', true, ['dependency']);
        $capabilities->register('dependency', true);
        self::assertFalse($capabilities->enabled('dependency'));
        $this->expectException(ConfigurationException::class);
        $capabilities->validate();
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app->useConfigPath($this->fixturePath.'/config');
    }

    public function test_application_configuration_is_not_overwritten_on_registration(): void
    {
        self::assertNotNull($this->app);
        $queue = ['connection' => null, 'queue' => 'application-notifications'];
        config(['notification-orchestrator.queue' => $queue]);

        (new NotificationOrchestratorServiceProvider($this->app))->register();

        self::assertSame($queue + ['tries' => 3, 'backoff' => 5], config('notification-orchestrator.queue'));
        self::assertFalse(config('notification-orchestrator.features.push'));
    }

    public function test_configuration_publication_preserves_an_existing_application_file(): void
    {
        $parameters = ['--tag' => 'notification-orchestrator-config'];
        $publish = $this->artisan('vendor:publish', $parameters);
        self::assertInstanceOf(PendingCommand::class, $publish);
        $publish->assertExitCode(0)->run();

        $destination = $this->fixturePath.'/config/notification-orchestrator.php';
        $files = new Filesystem;
        self::assertSame(
            $files->get(dirname(__DIR__, 2).'/config/notification-orchestrator.php'),
            $files->get($destination),
        );

        $customized = "<?php\nreturn ['application_override' => true];\n";
        $files->put($destination, $customized);
        $republish = $this->artisan('vendor:publish', $parameters);
        self::assertInstanceOf(PendingCommand::class, $republish);
        $republish->assertExitCode(0)->run();

        self::assertSame($customized, $files->get($destination));
    }

    public function test_configuration_can_be_cached_and_loaded_by_laravel(): void
    {
        $app = $this->app;
        self::assertNotNull($app);
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->fixturePath.'/bootstrap/cache');
        $files->ensureDirectoryExists($this->fixturePath.'/config');
        $overrides = ['features' => ['blade' => false], 'api' => ['middleware' => []],
            'channels' => ['types' => ['record.created' => []]], 'database' => ['connection' => null]];
        $files->put($this->fixturePath.'/config/notification-orchestrator.php',
            '<?php return '.var_export($overrides, true).';');
        $files->put($this->fixturePath.'/bootstrap/app.php', <<<'PHP'
<?php

return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withProviders([Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider::class])
    ->create();
PHP);
        $app->useBootstrapPath($this->fixturePath.'/bootstrap');
        $expected = (new ConfigurationMerger)->merge(require dirname(__DIR__, 2).'/config/notification-orchestrator.php', $overrides);

        $cache = $this->artisan('config:cache');
        self::assertInstanceOf(PendingCommand::class, $cache);
        $cache->assertExitCode(0)->run();

        $cachedApp = new Application($this->fixturePath);
        self::assertTrue($cachedApp->configurationIsCached());
        (new LoadConfiguration)->bootstrap($cachedApp);
        (new NotificationOrchestratorServiceProvider($cachedApp))->register();

        self::assertSame($expected, $cachedApp->make(Repository::class)->get('notification-orchestrator'));
    }
}
