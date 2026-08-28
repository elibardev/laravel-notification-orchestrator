# Public Testing API Specification

## Status

Draft v0.1 --- accepted architectural direction.

## Objective

Applications using the package must be able to test notification
behavior without requiring:

-   a running Reverb server;
-   FCM credentials;
-   an MQTT broker;
-   SMTP;
-   actual queue workers;
-   package database writes unless the test explicitly targets
    persistence.

The testing experience should feel Laravel-native.

## Primary fake API

``` php
Notify::fake();
```

After `fake()`, notification orchestration is intercepted by an
in-memory fake.

Planning still happens during `send()`. Recording for assertions happens at
the execution boundary: immediately without a transaction, or after the
outermost successful commit. Rollback leaves no recorded send. See
[ADR-0034](adr/0034-planning-and-after-commit-execution.md).

Application business logic can execute normally.

Example:

``` php
Notify::fake();

$service->createIncident($data);

Notify::assertSent('incident.created');
```

## Core assertions

### assertSent

``` php
Notify::assertSent('incident.created');
```

Asserts at least one logical notification of the given semantic type was
sent.

Support application enums:

``` php
Notify::assertSent(
    NotificationType::INCIDENT_CREATED
);
```

### assertNotSent

``` php
Notify::assertNotSent('incident.created');
```

### assertNothingSent

``` php
Notify::assertNothingSent();
```

### assertSentTimes

``` php
Notify::assertSentTimes(
    'incident.created',
    1
);
```

This counts logical notification dispatches, not recipients or channel
jobs.

## Recipient assertions

### assertSentTo

``` php
Notify::assertSentTo(
    $user,
    'incident.created'
);
```

### assertNotSentTo

``` php
Notify::assertNotSentTo(
    $user,
    'incident.created'
);
```

### Predicate support

``` php
Notify::assertSentTo(
    $user,
    'incident.created',
    function (RecordedNotification $notification) {
        return $notification->payload->data['property_id'] === 678;
    }
);
```

## Payload assertions

General predicate:

``` php
Notify::assertSent(
    'incident.created',
    fn (RecordedNotification $notification) =>
        $notification->payload->title === 'Nueva incidencia'
        && $notification->payload->severity->value === 'info'
);
```

Potential convenience assertions:

``` php
Notify::assertPayload(
    'incident.created',
    fn (NotificationPayload $payload) => ...
);
```

Recommendation:

Keep v0.1 assertion surface small. Predicates provide extensibility
without dozens of specialized assertion methods.

## Recipient resolution testing

The fake should run real recipient resolution by default.

This makes:

``` php
Notify::assertSentTo($auditor, ...)
```

meaningful for `RecipientResolver` business rules.

Optional mode:

``` php
Notify::fake(resolveRecipients: false);
```

is not required initially.

## Channel-plan assertions

The fake should execute planning but not delivery.

This allows assertions such as:

``` php
Notify::assertPlannedFor(
    $user,
    'push'
);
```

and:

``` php
Notify::assertSkippedFor(
    $user,
    'push',
    SkipReason::USER_PREFERENCE
);
```

Recommended initial public methods:

``` php
Notify::assertChannelPlanned(
    object $recipient,
    string $channel
);

Notify::assertChannelSkipped(
    object $recipient,
    string $channel,
    ?SkipReason $reason = null
);
```

These verify `DeliveryPlan`, not provider execution.

## Context delivery assertions

### Broadcast context

``` php
Notify::assertBroadcastTo(
    'incident.347'
);
```

### Generic context target

``` php
Notify::assertContextSent(
    transport: 'mqtt',
    destination: 'incidents/347'
);
```

Predicate form:

``` php
Notify::assertContextSent(
    'mqtt',
    fn (ContextDeliveryPlan $plan) =>
        $plan->destination === 'incidents/347'
        && $plan->options['qos'] === 1
);
```

## Actions assertions

Use the recorded payload:

``` php
Notify::assertSent(
    'property.map.authorization_required',
    fn (RecordedNotification $notification) =>
        collect($notification->payload->actions)
            ->contains(fn ($action) =>
                $action->id === 'view_map'
            )
);
```

No dedicated assertion is required initially.

## RecordedNotification

The fake records a normalized representation:

``` php
final readonly class RecordedNotification
{
    public function __construct(
        public NotificationContext $context,
        public NotificationPayload $payload,
        public NotificationDispatchPlan $plan,
        public NotificationDispatchResult $result,
    ) {}
}
```

This gives advanced tests access to the full orchestration result.

## Fake behavior

`Notify::fake()` should:

``` text
run builder validation
normalize context
resolve recipients
apply exclusions
apply recipient filters
resolve preferences
build DeliveryPlans
build ContextDeliveryPlans
record dispatch only at the execution boundary
DO NOT invoke real delivery providers
```

Recording follows the transaction boundary above; it is not an immediate
planning side effect. `send()` still returns an immutable planning result
before commit, including `plannedQueueJobCount`. No normal inbox or tracking
writes occur, and resolvers are not rerun when the fake records after commit.

Database persistence should also be suppressed by default because the
goal is unit/feature business testing.

Tests that specifically validate persistence should not use the global
orchestrator fake.

## Provider-specific fakes

Separate infrastructure tests may use driver fakes.

Examples:

``` php
Push::fake();
Mqtt::fake();
```

These names are conceptual.

Recommendation:

Do not expose provider-specific facades in v0.1 unless implementation
demonstrates a clear need.

Instead, allow fake channel implementations to be registered in the
`ChannelRegistry` for integration tests.

## Testing persistence

For persistence integration tests:

``` text
use real database feature
fake broadcast/external channels
```

Assertions can inspect repository/API behavior:

``` php
$this->assertDatabaseHas('notify_notifications', [...]);
```

or package testing helpers may later expose:

``` php
Notifications::assertStoredFor(...)
```

This is not required for initial v0.1 public testing API.

## Testing read/unread

Recommended public HTTP tests:

``` php
$this->actingAs($user)
    ->patchJson("/notifications/{$id}/read")
    ->assertOk()
    ->assertJsonPath('state.read', true);
```

Repository unit tests use the real `NotificationRepository`.

## Testing realtime synchronization

Do not require Reverb.

Test the package's broadcast event/envelope generation with Laravel
Broadcast/Event fakes and package-level protocol assertions.

Example concepts:

``` text
markRead()
-> persisted read state
-> emits notification.read envelope
-> contains authoritative unread_count
```

## Testing after-commit behavior

The package test suite must verify:

``` text
send() inside transaction
-> resolve and plan once; return result; assertNothingSent() still passes

outermost transaction commit
-> record prepared dispatch; assertSent() passes

transaction rollback
-> discard pending recording; assertNothingSent() passes

inner commit
-> no recording until outermost commit

inner rollback
-> discard recordings created in that transaction scope
```

This is mandatory because automatic after-commit semantics are part of
the public behavior.

Also verify final-recipient counts, asynchronous recipient/channel job counts
(not device counts), separate context counts and unchanged result objects after
commit/rollback. Mutating application state after `send()` must not change
already planned recipients/preferences. Real integration tests must assert no
inbox, tracking, queue or publication effects before commit, even for sync queues.

Identity tests must distinguish one logical `payload.id` from each recipient's
`storedNotificationId`; reading one recipient's inbox must not affect another.

## Testing custom channels

Third-party/custom channel authors should receive a reusable contract
test base or documented conformance suite.

A custom recipient channel should be tested for:

``` text
registration
duplicate-name behavior
configuration validation
health status
destination resolution
successful send result
failed send result
status command integration
```

A custom context transport should be tested similarly.

## Orchestra Testbench

The package's own tests use Orchestra Testbench.

Application consumers do not need to install Testbench merely to use
`Notify::fake()`.

## Test isolation

`Notify::fake()` must reset between PHPUnit/Pest test processes/tests
through normal Laravel application lifecycle.

No static state should leak between tests.

## Initial accepted public test surface

``` php
Notify::fake();

Notify::assertSent(...);
Notify::assertNotSent(...);
Notify::assertNothingSent();
Notify::assertSentTimes(...);

Notify::assertSentTo(...);
Notify::assertNotSentTo(...);

Notify::assertChannelPlanned(...);
Notify::assertChannelSkipped(...);

Notify::assertBroadcastTo(...);
Notify::assertContextSent(...);
```

Advanced inspection is available through recorded notifications.
