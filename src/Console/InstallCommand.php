<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Console;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'notifications:install';

    protected $description = 'Publish missing feature resources without overwriting existing files or migrating.';

    public function handle(Configuration $config, Filesystem $files): int
    {
        $config->validate();
        $root = dirname(__DIR__, 2);
        $this->copy($files, $root.'/config/notification-orchestrator.php', $this->laravel->configPath('notification-orchestrator.php'));
        $migrationPath = $this->laravel->databasePath('migrations');
        $files->ensureDirectoryExists($migrationPath);
        foreach (['database' => 'notifications', 'preferences' => 'preferences', 'delivery_tracking' => 'deliveries', 'devices' => 'devices'] as $feature => $table) {
            if (! $config->enabled($feature)) {
                continue;
            }
            $suffix = 'create_orchestrator_'.$table.'_table.php';
            if ($files->glob($migrationPath.'/*_'.$suffix) !== []) {
                $this->line($table.': migration retained');

                continue;
            }
            $this->copy($files, $root.'/database/migrations/'.$suffix.'.stub', $migrationPath.'/'.date('Y_m_d_His').'_'.$suffix);
        }
        if ($config->enabled('blade')) {
            foreach (['resources/views' => $this->laravel->resourcePath('views/vendor/notifications'),
                'resources/js' => $this->laravel->publicPath('vendor/notification-orchestrator/js'),
                'resources/css' => $this->laravel->publicPath('vendor/notification-orchestrator/css')] as $source => $target) {
                if (! $files->isDirectory($root.'/'.$source)) {
                    continue;
                }
                foreach ($files->allFiles($root.'/'.$source) as $file) {
                    $this->copy($files, $file->getPathname(), $target.'/'.$file->getRelativePathname());
                }
            }
        }
        $this->info('Existing resources preserved. No migrations executed.');
        $this->line('Next: php artisan migrate; php artisan notifications:status');

        return self::SUCCESS;
    }

    private function copy(Filesystem $files, string $source, string $target): void
    {
        if ($files->exists($target)) {
            $this->line(basename($target).': retained');

            return;
        }
        $files->ensureDirectoryExists(dirname($target));
        $files->copy($source, $target);
        $this->line(basename($target).': published');
    }
}
