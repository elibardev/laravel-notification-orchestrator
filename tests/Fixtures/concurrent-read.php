<?php

declare(strict_types=1);
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Foundation\Application;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$factory = new class extends Orchestra\Testbench\Foundation\Application
{
    /** @param Application $app */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => $_SERVER['argv'][1], 'busy_timeout' => 10000]);
    }
};
$factory->configure(['extra' => ['providers' => [NotificationOrchestratorServiceProvider::class]]])->createApplication();
$result = app(NotificationRepository::class)->markRead(
    new RecipientIdentity('account', 'concurrent'), $_SERVER['argv'][2]);
echo json_encode(['changed' => $result->changed, 'unread_count' => $result->unreadCount], JSON_THROW_ON_ERROR);
