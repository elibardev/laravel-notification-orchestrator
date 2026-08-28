<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

class OperationsTest extends TestCase
{
    public function test_install_partial_rerun_and_later_features_preserve_user_files(): void
    {
        self::assertNotNull($this->app);
        $this->app->useConfigPath($this->fixturePath.'/config');
        $this->app->useDatabasePath($this->fixturePath.'/database');
        $kernel = app(Kernel::class);
        $files = new Filesystem;
        self::assertSame(0, $kernel->call('notifications:install'));
        self::assertFalse(app(Storage::class)->available('notifications'));
        $path = $files->glob($this->fixturePath.'/database/migrations/*.php')[0];
        $files->append($path, "\n// application customization\n");
        $files->put($this->fixturePath.'/config/notification-orchestrator.php', "<?php return ['custom' => true];");
        config(['notification-orchestrator.features.preferences' => true, 'notification-orchestrator.features.delivery_tracking' => true]);
        self::assertSame(0, $kernel->call('notifications:install'));
        self::assertSame(0, $kernel->call('notifications:install'));
        self::assertCount(3, $files->glob($this->fixturePath.'/database/migrations/*.php'));
        self::assertStringContainsString('application customization', $files->get($path));
        self::assertStringContainsString("'custom' => true", $files->get($this->fixturePath.'/config/notification-orchestrator.php'));
        self::assertSame(0, $kernel->call('migrate', ['--path' => $this->fixturePath.'/database/migrations', '--realpath' => true, '--force' => true]));
        foreach (['notifications', 'preferences', 'deliveries'] as $name) {
            self::assertTrue(app(Storage::class)->available($name));
        }
        self::assertSame(0, $kernel->call('notifications:status'));
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'notification-orchestrator.features.devices' => true]);
        self::assertSame(0, $kernel->call('notifications:install'));
        self::assertSame(0, $kernel->call('notifications:install'));
        self::assertCount(4, $files->glob($this->fixturePath.'/database/migrations/*.php'));
        self::assertSame(0, $kernel->call('migrate', ['--path' => $this->fixturePath.'/database/migrations', '--realpath' => true, '--force' => true]));
        self::assertTrue(app(Storage::class)->available('devices'));
        self::assertSame(0, $kernel->call('notifications:status'));
    }

    public function test_prune_defaults_dry_run_chunks_and_recipient_scoped_cleanup(): void
    {
        config(['notification-orchestrator.features.delivery_tracking' => true, 'notification-orchestrator.delivery_tracking.channels.database' => true, 'notification-orchestrator.retention.chunk_size' => 1]);
        app(PackageSchema::class)->create('notifications');
        app(PackageSchema::class)->create('deliveries');
        $a = new RecipientIdentity('account', 'a');
        $b = new RecipientIdentity('account', 'b');
        $this->travelTo(Carbon::parse('2026-01-01', 'UTC'));
        Notify::make('x')->title('T')->message('M')->recipients([$a, $b])->send();
        app(NotificationRepository::class)->markAllRead($a);
        $this->travelTo(Carbon::parse('2026-05-01', 'UTC'));
        $kernel = app(Kernel::class);
        self::assertSame(0, $kernel->call('notifications:prune', ['--only' => 'notifications']));
        self::assertSame(2, app(Storage::class)->table('notifications')->count());
        config(['notification-orchestrator.retention.notifications.enabled' => true, 'notification-orchestrator.retention.notifications.days' => 30]);
        $before = app(Storage::class)->table('deliveries')->count();
        self::assertSame(0, $kernel->call('notifications:prune', ['--only' => 'notifications', '--dry-run' => true]));
        self::assertSame(2, app(Storage::class)->table('notifications')->count());
        self::assertSame($before, app(Storage::class)->table('deliveries')->count());
        self::assertSame(0, $kernel->call('notifications:prune', ['--only' => 'notifications']));
        self::assertSame(1, app(Storage::class)->table('notifications')->count());
        self::assertSame(1, app(NotificationRepository::class)->unreadCount($b));
        self::assertSame(0, app(Storage::class)->table('deliveries')->where('notifiable_id', 'a')->count());
        self::assertGreaterThan(0, app(Storage::class)->table('deliveries')->where('notifiable_id', 'b')->count());
        self::assertSame(0, $kernel->call('notifications:prune', ['--only' => 'deliveries']));
        self::assertSame(0, app(Storage::class)->table('deliveries')->count());
        self::assertSame(1, app(Storage::class)->table('notifications')->count());
        self::assertSame(2, $kernel->call('notifications:prune', ['--only' => 'invalid']));
    }
}
