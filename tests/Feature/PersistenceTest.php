<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Configuration\TableNameResolver;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Persistence\DatabaseNotificationRepository;
use Elibardev\NotificationOrchestrator\Persistence\NotificationQuery;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlanner;
use Elibardev\NotificationOrchestrator\Preferences\DatabasePreferenceRepository;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Database\DatabaseManager;
use Illuminate\Notifications\DatabaseNotification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Process;

class PersistenceTest extends TestCase
{
    public function test_two_php_processes_mark_read_concurrently_without_double_changes(): void
    {
        $path = $this->fixturePath.'/concurrent.sqlite';
        touch($path);
        config(['database.connections.concurrent' => ['driver' => 'sqlite', 'database' => $path, 'busy_timeout' => 10000], 'notification-orchestrator.database.connection' => 'concurrent']);
        app(PackageSchema::class)->create('notifications');
        $owner = new RecipientIdentity('account', 'concurrent');
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [$owner], planningOnly: true);
        $stored = app(DatabaseNotificationRepository::class)->store($plan->recipients[0]);
        $command = [PHP_BINARY, dirname(__DIR__).'/Fixtures/concurrent-read.php', $path, $stored->id];
        $first = new Process($command);
        $second = new Process($command);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        self::assertTrue($first->isSuccessful(), $first->getErrorOutput());
        self::assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $one = json_decode($first->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $two = json_decode($second->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, (int) $one['changed'] + (int) $two['changed']);
        self::assertSame(0, $one['unread_count']);
        self::assertSame(0, $two['unread_count']);
        app(DatabaseManager::class)->disconnect('concurrent');
    }

    public function test_native_laravel_model_can_read_the_schema_and_json_objects_survive_projection(): void
    {
        app(PackageSchema::class)->create('notifications');
        $owner = new RecipientIdentity('account', 'a');
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [$owner], planningOnly: true);
        $stored = app(DatabaseNotificationRepository::class)->store($plan->recipients[0]);
        $model = new DatabaseNotification;
        $model->setTable(app(TableNameResolver::class)->for('notifications'));
        self::assertSame($plan->payload->id, $model->newQuery()->findOrFail($stored->id)->getAttribute('data')['id']);
        self::assertStringContainsString('"data":{}', json_encode($stored->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_personal_ids_payload_read_state_and_retry_are_isolated(): void
    {
        app(PackageSchema::class)->create('notifications');
        $a = new RecipientIdentity('account', 'alpha');
        $b = new RecipientIdentity('account', 'beta');
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('record.created', 'Title', 'Message'), [[$a, $b]], planningOnly: true);
        $repo = app(DatabaseNotificationRepository::class);
        $first = $repo->store($plan->recipients[0]);
        $second = $repo->store($plan->recipients[1]);
        self::assertNotSame($first->id, $second->id);
        self::assertSame($first->payload['id'], $second->payload['id']);
        self::assertSame($first->id, $first->toArray()['id']);
        self::assertNull($repo->findFor($b, $first->id));
        $read = $repo->markRead($a, $first->id);
        self::assertTrue($read->changed);
        self::assertFalse($repo->markRead($a, $first->id)->changed);
        self::assertSame($read->notification->readAt, $repo->store($plan->recipients[0])->readAt);
        self::assertSame(1, $repo->unreadCount($b));
        self::assertSame(0, $repo->unreadCount($a));
        self::assertTrue($repo->markUnread($a, $first->id)->changed);
        self::assertFalse($repo->markUnread($a, $first->id)->changed);
        self::assertSame(1, $repo->markAllRead($a)->changed);
        self::assertSame(0, $repo->markAllRead($a)->changed);
        self::assertSame(1, $repo->unreadCount($b));
        $this->expectException(NotFoundHttpException::class);
        $repo->markRead($b, $first->id);
    }

    public function test_cursor_has_stable_tie_breaking_and_filters(): void
    {
        app(PackageSchema::class)->create('notifications');
        $a = new RecipientIdentity('account', 'a');
        $repo = app(DatabaseNotificationRepository::class);
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            $plan = app(DeliveryPlanner::class)->plan(new NotificationContext($i === 4 ? 'other' : 'x', 'T', 'M'), [$a], planningOnly: true);
            $repo->store($plan->recipients[0]);
        }
        $one = $repo->paginateFor($a, new NotificationQuery(2, type: 'x'));
        $two = $repo->paginateFor($a, new NotificationQuery(2, type: 'x', cursor: $one->nextCursor));
        self::assertCount(4, array_unique(array_map(fn ($n) => $n->id, [...$one->items, ...$two->items])));
        self::assertNull($two->nextCursor);
        self::assertSame(5, $two->unreadCount);
        self::assertCount(0, $repo->paginateFor($a, new NotificationQuery(state: 'read'))->items);
    }

    public function test_global_preferences_upsert_and_reset_without_nullable_unique_keys(): void
    {
        app(PackageSchema::class)->create('preferences');
        $repo = app(DatabasePreferenceRepository::class);
        $a = new RecipientIdentity('a', '1');
        $b = new RecipientIdentity('a', '2');
        $repo->set($a, null, 'mail', true);
        $repo->set($a, null, 'mail', false);
        $repo->set($b, null, 'mail', true);
        $repo->set($a, 'x', 'mail', true);
        self::assertSame(3, app(Storage::class)->table('preferences')->count());
        self::assertFalse($repo->get($a, null, 'mail'));
        self::assertTrue($repo->get($a, 'x', 'mail'));
        $repo->delete($a, null, 'mail');
        self::assertNull($repo->get($a, null, 'mail'));
        self::assertTrue($repo->get($b, null, 'mail'));
    }

    public function test_schema_uses_prefix_and_explicit_overrides(): void
    {
        config(['notification-orchestrator.database.table_prefix' => 'custom_', 'notification-orchestrator.database.tables.preferences' => 'personal_prefs']);
        foreach (['notifications', 'preferences', 'deliveries'] as $name) {
            app(PackageSchema::class)->create($name);
            self::assertTrue(app(Storage::class)->available($name));
        }
        self::assertTrue(app(Storage::class)->connection()->getSchemaBuilder()->hasTable('personal_prefs'));
        app(PackageSchema::class)->drop('notifications');
        self::assertFalse(app(Storage::class)->available('notifications'));
    }
}
