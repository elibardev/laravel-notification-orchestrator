# Phase 2 — Persistent inbox, clients and operations

Implemented increments 2.1–2.4. Development target: Herd PHP 8.2.31 / Laravel 12.
Phase 3 was explicitly authorized while final Phase 2 verification was running.
This guide records the Phase 2 boundary; see PHASE-3.md for subsequent modules.

## Installation

Inside the consuming Laravel application:

```sh
php artisan notifications:install
php artisan migrate
php artisan notifications:status
```

Install publishes only missing resources. It does not execute migrations, replace
modified files or install a scheduler. Re-run after enabling preferences, tracking
or Blade. Three conditional migrations are included: notifications (database),
preferences and deliveries (delivery_tracking). TableNameResolver controls prefix
and explicit names; do not change names after migration without a data migration.
No user FK is assumed. IDs of notifiables are stored as strings with morph type.
Laravel jobs/failed_jobs tables remain application infrastructure.

The inbox schema is readable by Laravel DatabaseNotification with its table set
to TableNameResolver::for('notifications'). Laravel's default Notifiable relation
still targets its default notifications table: use the package repository/API or
explicitly adapt that relation. Direct native model writes bypass package events.

Default middleware is ['web', 'auth']; include a csrf-token meta tag in Blade layouts.
For token authentication replace api.middleware with the application's guard
stack. Sanctum is optional. Publishing tags: notification-orchestrator-config,
notification-orchestrator-views, notification-orchestrator-assets. Avoid --force
unless intentional. Changes to route-affecting features require route:clear or a
fresh route:cache, and config cache must be rebuilt after configuration changes.

## Repository and identity

```php
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Persistence\NotificationQuery;

$repository = app(NotificationRepository::class);
$page = $repository->paginateFor($user, new NotificationQuery(limit: 20, state: 'unread'));
$change = $repository->markRead($user, $personalInboxId);
$repository->markUnread($user, $personalInboxId);
$repository->markAllRead($user);
```

All owner parameters use RecipientNormalizer. No active/email/tenant columns are
assumed. findFor returns null for foreign or missing IDs; state mutations return
404 for both. markRead/unread/all are idempotent. NotificationQuery accepts all,
read or unread, type, limit 1–100 and an opaque cursor; ordering is created_at/id
DESC. Cursor responses include next_cursor and authoritative unread_count.

Personal resource id differs from logical payload.id. Stored JSON keeps schema
1.0 and logical identity; resources project personal IDs and add state/created_at.
Empty data objects remain JSON objects. Retry preserves read_at and the original
inbox row. Provider success never implies reading.

## HTTP protocol

Prefix defaults to /api/notifications; names default to notifications.*.

| Method | Suffix | Response |
| --- | --- | --- |
| GET | /bootstrap | notifications[], meta.unread_count, meta.next_cursor, realtime |
| GET | / | notifications[], meta.unread_count, meta.next_cursor |
| GET | /unread-count | meta.unread_count |
| PATCH | /{personalId}/read | notification_id, state, meta.unread_count |
| PATCH | /{personalId}/unread | same |
| POST | /read-all | changed, state.read_at, meta.unread_count |
| GET/PUT/DELETE | /preferences | type, channel, configured, effective, source |
| POST | /broadcasting/auth | Laravel broadcaster authentication response |

Preferences routes exist only with features.preferences; broadcasting auth only
with features.broadcast. GET/DELETE preferences accept channel and optional type;
PUT also requires boolean enabled. Null type is global. DELETE restores
inheritance. Structural channels are rejected. Owner always comes from
AuthenticatedNotifiableResolver, never request user_id. Bind that contract for a
custom authenticated owner mapping.

## Realtime and queue

Personal events: notification.created, notification.read, notification.unread,
notification.read_all. Envelopes carry schema, event, occurred_at and authoritative
meta.unread_count. Created includes the personal resource; read/unread include
notification_id and state. Read-all includes state.read_at. Subscribe using the
bootstrap realtime.channel through Echo.private; set the Echo auth endpoint to
bootstrap realtime.auth_endpoint and use the application's authorized guard.
No context authorization is granted by this endpoint.

Execution persists all inbox rows before dependent channels. One queued job per
recipient/channel carries frozen identity, payload, destinations and queue names;
multiple destinations execute within that job. queue.tries defaults to 3 and
queue.backoff to 5 seconds. Database workers must listen on queue.queue (default
notifications). Sync/queue-disabled paths retain after-commit behavior. Nested
rollback cancels work. RuntimeHealth reports missing application jobs tables.

Tracking stores one deterministic ID per logical notification/owner/channel/
destination hash. It works without inbox. Sent destinations are skipped on retry
when tracking exists; without tracking providers remain at-least-once. It stores
fingerprints, not raw destinations. Queue payloads may contain destinations and
must be protected with normal database/queue/failed_jobs access controls.
Provider exceptions are replaced by fixed safe messages; success clears errors.
DeliveryTransitionGuard forbids unsupported state transitions. Delivered requires
an explicit provider result/confirmation. Laravel controls retry scheduling.

## Optional frontend

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
<x-notifications::bell />
<x-notifications::inbox />
<x-notifications::toast-container />
```

Enable features.blade and run install to publish ES modules/CSS. Styles can be
disabled with blade.styles=false. No framework or bundler is required. Views use
semantic controls, keyboard handling and scoped CSS. Components share a client
per API base within the page. Toast rendering never marks read.

```js
import { createNotificationClient } from './notification-client.js';
const client = createNotificationClient({ apiBaseUrl: '/api/notifications', echo: window.Echo });
client.on('change', state => render(state));
client.actions.register('approve', action => callAuthorizedApplicationEndpoint(action));
await client.bootstrap();
```

Client methods: bootstrap/synchronize/reconnect, markRead/markUnread/markAllRead,
loadMore, receive, on, destroy, actions.register/execute. state exposes items,
unreadCount and nextCursor. Events: change, created, error. Pass fetch/headers for
custom authentication. Native clients can use HTTP without any assets or Blade.

Echo events trigger authoritative API refresh; stale counters are never applied
arithmetically. In-flight refreshes coalesce and repeat after invalidation. No
polling is installed. Echo reconnect/subscribed signals refresh state. Application
code can call reconnect for another transport. New-event toast dedupe remembers
the most recent 500 personal IDs; inbox items dedupe by ID. Destroy removes owned
listeners, not shared Echo channels. Call it on logout/account changes.

Navigate supports relative paths and HTTP(S), rejects executable/protocol-relative
URLs. Commands run only registered application handlers by action.id. Neither
kind grants permission; destination endpoints must authorize independently.

## Operation and evidence

notifications:status is safe before migrations and reports HEALTHY/DEGRADED/INVALID.
It checks storage availability and enabled modules without logging credentials.
This is configuration/storage evidence, not proof of remote subscriber rendering.

notifications:prune supports --dry-run and --only=notifications|deliveries. Inbox
retention is disabled by default; enabling requires positive days and preserves
unread unless only_read=false is explicit. Deliveries default to 90 days; null
disables their pruning. retention.chunk_size defaults to 500. Related delivery
cleanup uses logical ID AND owner. No scheduler is registered automatically.

Laravel events: StoredNotificationCreated, InboxChanged, DeliveryStateChanged.
Structured logs include dispatch IDs/correlation and sanitized failure/prune
summaries, never full payloads or provider exception messages.

Verification at the Phase 2 boundary: 73 PHPUnit tests / 337 assertions, including
two PHP processes concurrently marking the same SQLite row, real database queue
worker/retry, commit/rollback, ownership/private authentication, install reruns and
pruning. Six Node tests cover client protocol, races, actions and Echo lifecycle.
Browser checks used the actual Laravel API, two clients and a simulated Echo
transport: unread toast, counters, explicit read/action, reconnect and old events.
No-Echo mode and keyboard Enter/Escape were exercised; headless loads no package
views. Reverb/SMTP/FCM/Mosquitto were not contacted. SQLite is the verified engine;
MySQL/MariaDB/PostgreSQL deployment validation is not claimed.

Reproduce browser testing with herd php tests/browser/server.php then
herd php -S 127.0.0.1:8792 tests/browser/server.php. Fixture routes and identity are
local test code only, never registered by the package. Data lives in .cache/browser.

See ADR-0035 for physical keys and runtime refinements. No outbox/dispatch/attempt
history/context table was added. After-commit retains its documented crash window.
