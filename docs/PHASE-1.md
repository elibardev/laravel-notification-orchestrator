# Phase 1 — implementation and review

Historical snapshot of the Phase 1 boundary, not the current package status.
Runtime providers and persisted storage described as absent below now exist;
see [PHASE-2.md](PHASE-2.md) and [PHASE-3.md](PHASE-3.md). In particular, fakes now
validate enabled adapter configuration; inject controlled clients/drivers.

Configuration, semantic objects, recipient/channel/context planning, the public
API and fake, diagnostics and the transaction boundary are implemented. This phase
does **not** implement production inbox storage, workers, HTTP routes, Blade or
external delivery providers. No package migrations have been introduced.

## Public entry points

Application test example (not a production delivery example):

```php
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\NotificationAction;

$fake = Notify::fake();
$result = Notify::make('record.created')
    ->title('New record')
    ->message('A record was created.')
    ->recipients($user)
    ->action(NotificationAction::navigate('open', 'Open', '/records/1'))
    ->send();

Notify::assertSentTo($user, 'record.created');
Notify::assertChannelPlanned($user, 'database');
$record = $fake->recorded()[0];
```

`$user` is a keyed Eloquent model. UUID models must declare a string key. No
`users` table or active/tenant/role/deleted columns are assumed or queried by
recipient normalization. Other objects use a custom `Contracts\RecipientNormalizer`.

Dependency injection shares the same service and fake:

```php
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationDispatcher;
use Elibardev\NotificationOrchestrator\NotificationOrchestrator;

$service = app(NotificationOrchestrator::class);
$fake = $service->fake();
app(NotificationDispatcher::class)->dispatch(
    new NotificationContext('record.created', 'New record', 'A record was created.'),
    $user,
);
$fake->assertSent('record.created');
```

`send()` is the only fluent terminal. Provisional conveniences (`channel()`,
`contextTargets()`, builder `plan()`, `assertPayload()`) are not promoted into the
public API. Advanced planning uses `Planning\DeliveryPlanner`.

All accepted fake assertions are implemented. Assertions require PHPUnit, not
Testbench. Calling `fake()` again creates a fresh recorder; pending callbacks retain
the old recorder. Application lifecycle resets the fake; no global recorder is used.

## Recorded implementation choices

These refine provisional details within the accepted ADRs; no ADR is superseded.
All configuration keys below are under `notification-orchestrator`.

| Setting | Meaning |
| --- | --- |
| `channels.defaults` | Global requested optional channels; default `[]`. |
| `channels.types` | Complete semantic type, including dots, to channel lists. |
| `channels.destinations` | Channel to `Contracts\ChannelDestinationResolver` class. |
| `recipients.normalizer` | `Contracts\RecipientNormalizer` implementation class. |
| `recipients.filters` | Ordered `Contracts\RecipientFilter` class list. |
| `references.normalizer` | `Contracts\ReferenceNormalizer` implementation class. |
| `preferences.default` | Package fallback, initially `true`. |
| `preferences.defaults` | Application channel defaults. |
| `preferences.types` | Application type/channel defaults. |

`features.*` is the sole activation mechanism. Maps merge recursively; lists
replace entirely, including `[]`. Valid false/null values survive. Cache behavior
matches uncached configuration. Duplicate module `.enabled` keys are errors.
Use named classes in configuration, not closures or anonymous classes.

The inbox API requires database capability; managed Blade requires the API.
Dependencies are never enabled implicitly. Preferences/tracking/devices do not
require inbox persistence, and external push resolution does not require devices.
`Configuration\TableNameResolver` resolves portable identifiers and explicit
overrides for the four approved logical tables, without creating them.

`Preferences\InMemoryPreferenceRepository`, behind `Contracts\PreferenceRepository`,
is transient Phase 1 storage, not a production preference database. User lookups
apply only with `features.preferences`; application defaults remain available.
Structural channels reject user preference writes. Preferences never add channels.

## Registry extension contract

Register extensions in an application/package provider before boot validation.
Built-in names cannot be replaced. A new channel/transport registers its capability
name, but activation still requires `features.<name>`.

```php
use App\Notifications\ExampleChannel;
use App\Notifications\ExampleDestinationResolver;
use Elibardev\NotificationOrchestrator\Channels\ChannelDefinition;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;

app(ChannelRegistry::class)->register(
    new ChannelDefinition('example', ChannelKind::OPTIONAL, 'example-driver',
        preferenceAware: true, requiresDestination: true,
        queueable: true, healthCheckable: true),
    ExampleChannel::class,
    ExampleDestinationResolver::class,
);
```

The `App` classes are application implementations, not supplied package classes.
Implement `Contracts\NotificationChannel` and `Contracts\ChannelDestinationResolver`.
Validation must not deliver. `send()` receives `Channels\ChannelDelivery` containing
frozen recipient/channel plans. Resolvers return `Channels\ChannelDestination`.

Conformance examples: `tests/Fixtures/TestChannel.php`,
`tests/Feature/PlanningTest.php` and `tests/Feature/StatusTest.php` cover duplicate
registration, disabled providers, validation, destinations, preferences and health.
Context extensions implement `Contracts\ContextDeliveryTransport` and register
through `Context\ContextTransportRegistry`; see `tests/Fixtures/TestTransport.php`.
Neither extension requires modifying the planner.

## Identity and safety

- Logical payload/result IDs are UUIDs. Separate personal `storedNotificationId`
  values exist only when persistence is planned; this phase writes no inbox rows.
- Plans retain `Recipients\RecipientIdentity`, not live recipient models.
  Dates, arrays and destinations are snapshots. JSON data rejects objects,
  resources, invalid values and excessive recursion; root data is always an object.
- Scalar actors expose only ID; Eloquent actors do not export attributes. Subjects
  require `SubjectReference`, a morph-mapped model or a custom normalizer. PHP
  class names are not exported as default semantic subject types.
- Personal broadcast destinations require a morph alias or custom resolver. The
  default pattern identifies both type and ID. Authorization remains application-owned.
- Action IDs are unique. Navigate URLs accept relative paths or HTTP(S), without
  granting permission. Clients must escape content and authorize commands.
- Diagnostics use fixed descriptions, not provider exception messages, full
  payloads, credentials or raw destinations.

Context plans do not use user preferences. Unknown transports throw; explicitly
disabled transports are configuration errors. MQTT targets require concrete topics,
QoS 0/1 and boolean retain, defaulting to QoS 1/retain false. No subscribers are
counted and no context-delivery table is introduced.

## Transactions and execution

`send()` plans once and computes one immutable result before scheduling execution.
The fake records that same result only when execution is due. An empty resolved
recipient set is valid when a source was supplied; omitting sources is invalid,
including context-only requests.

The boundary attaches to Laravel's native transaction record for the application's
default connection. It waits for the outermost commit and uses native nested
rollback cleanup. Selecting the exact record prevents another connection from
capturing the callback; this does not introduce distributed transactions.

Counts represent final recipients, asynchronous recipient/channel jobs and context
plans. Database persistence is planned synchronously. Sync/null queue drivers and
disabled queueing produce no asynchronous jobs. Queue connection/name are frozen;
channel-specific queue names override the global name. Multiple destinations do
not increase job count. No jobs are actually enqueued in Phase 1.

`Contracts\DeliveryExecutor` is the execution boundary. The default executor throws
rather than pretending to deliver. Tests can inject a controlled executor. The
public fake replaces execution, preserving configuration, recipients, preferences,
destinations and planning.

Built-ins have metadata but no delivery implementations. The fake can plan them
without real provider credentials; it still validates package configuration and
registered channel implementations. Normal dispatch and web boot reject enabled
unimplemented channels. Console boot permits diagnostics; dispatch still validates.

Planning errors surface during `send()`. Errors after commit cannot roll back
business data. The crash window after commit remains: no outbox, exactly-once
guarantee or durable acceptance receipt is added.

## Review boundary

See [TESTING.md](TESTING.md) for verification and runtime requirements.
`notifications:status` exits 0 for HEALTHY, 1 for INVALID and 2 for DEGRADED.
Default configuration correctly reports unimplemented operational modules as
INVALID; production delivery is not ready.

Phase 2 has not started. Review this phase before adding persistence, workers,
HTTP API, realtime execution and UI.
