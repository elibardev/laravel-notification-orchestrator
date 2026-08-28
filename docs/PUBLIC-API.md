# Public API Specification

## Status

Draft v0.1 --- proposed public contract for the 0.x implementation line.

See [PHASE-1.md](PHASE-1.md) for core imports, and [PHASE-2.md](PHASE-2.md) and
[PHASE-3.md](PHASE-3.md) for implemented persistence, HTTP and delivery adapters.
Sections explicitly described as proposed conveniences below are not automatically
part of the implemented surface. The default executor now performs delivery.

## Goals

The public API must be:

-   concise for common notification cases;
-   strongly typed internally;
-   composable;
-   framework-native;
-   independent of specific providers;
-   usable without requiring custom notification classes;
-   extensible without modifying package core;
-   stable enough to freeze at 1.0.

The package exposes two public usage styles:

1.  fluent builder API;
2.  explicit object/service API.

The fluent API is ergonomic sugar over the explicit API.

------------------------------------------------------------------------

## 1. Primary Facade

Public facade:

``` php
use Elibardev\NotificationOrchestrator\Facades\Notify;
```

Primary entry point:

``` php
Notify::make(string $type): NotificationBuilder
```

Example:

``` php
Notify::make('incident.created')
    ->title('Nueva incidencia')
    ->message('Se reportó una nueva incidencia.')
    ->recipients($users)
    ->send();
```

`type` is required and identifies the semantic notification type.

------------------------------------------------------------------------

## 2. NotificationBuilder

Proposed fluent API:

``` php
Notify::make('incident.created')
    ->title('Nueva incidencia')
    ->message('Se reportó una nueva incidencia.')
    ->severity(NotificationSeverity::INFO)
    ->occurredAt($incident->created_at)
    ->actor($user)
    ->subject($incident)
    ->data([
        'property_id' => 678,
    ])
    ->action(
        NotificationAction::navigate(
            id: 'view_incident',
            label: 'Ver incidencia',
            url: "/properties/678/incidents/347",
            data: [
                'incident_id' => 347,
            ],
        )
    )
    ->recipients(IncidentRecipients::class)
    ->except($actor)
    ->channels(['push', 'mqtt'])
    ->broadcastTo("incident.{$incident->id}")
    ->contextTo(
        ContextTarget::mqtt(
            "incidents/{$incident->id}",
            qos: 1,
            retain: false,
        )
    )
    ->send();
```

------------------------------------------------------------------------

## 3. Required fields

Required before `send()`:

``` text
type
title
message
recipients
```

At least one recipient source is required for durable recipient
notification delivery.

Exception:

A future context-only builder mode may permit zero recipients, but this
is not part of the initial public contract.

Defaults:

``` text
severity      = info
occurred_at   = now()
data          = {}
actions       = []
channels      = configured optional channel defaults
```

Structural channels are not specified by the builder.

------------------------------------------------------------------------

## 4. Builder methods

### title

``` php
->title(string $title)
```

Required.

### message

``` php
->message(string $message)
```

Required.

### severity

``` php
->severity(NotificationSeverity|string $severity)
```

Recommended public enum:

``` php
enum NotificationSeverity: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}
```

String values may be accepted for interoperability but are normalized
through the enum/value object internally.

### occurredAt

``` php
->occurredAt(DateTimeInterface $date)
```

Optional.

Defaults to current time.

### actor

``` php
->actor(object|string|int|null $actor)
```

Recommended implementation should accept a model/object and normalize it
through an actor transformer.

For portable payloads, the final actor representation is not the raw
Eloquent model.

### subject

``` php
->subject(object|string|int|null $subject)
```

The subject identifies the main domain entity.

Applications may configure/extend subject normalization.

### data

``` php
->data(array $data)
```

Replaces current custom payload data.

### mergeData

Recommended convenience:

``` php
->mergeData(array $data)
```

Merges additional domain metadata.

### action

``` php
->action(NotificationAction $action)
```

Adds one action.

Can be called multiple times.

### actions

``` php
->actions(iterable $actions)
```

Adds multiple actions.

------------------------------------------------------------------------

## 5. Actions

### Navigate action

``` php
NotificationAction::navigate(
    id: 'view_incident',
    label: 'Ver incidencia',
    url: '/incidents/347',
    data: [
        'incident_id' => 347,
    ],
);
```

### Command action

``` php
NotificationAction::command(
    id: 'approve_map',
    label: 'Autorizar',
    data: [
        'property_id' => 676,
    ],
);
```

Action contract:

``` text
id      required
type    required
label   required
url     optional
data    optional {}
```

Initial action types:

``` text
navigate
command
```

Actions do not grant authorization.

------------------------------------------------------------------------

## 6. Recipient API

### Single notifiable

``` php
->recipients($user)
```

### Collection / iterable

``` php
->recipients($users)
```

### Resolver class

``` php
->recipients(IncidentRecipients::class)
```

### Resolver instance

``` php
->recipients(new IncidentRecipients())
```

### Closure

``` php
->recipients(
    fn (NotificationContext $context) =>
        User::role('auditor')->get()
)
```

Multiple calls compose recipient sources:

``` php
->recipients($incident->participants)
->recipients($incident->technician)
->recipients(User::role('auditor')->get())
```

They are normalized and deduplicated before planning.

------------------------------------------------------------------------

## 7. RecipientResolver

``` php
interface RecipientResolver
{
    public function resolve(
        NotificationContext $context
    ): iterable;
}
```

The application owns business-specific recipient logic.

------------------------------------------------------------------------

## 8. Exclusions

Proposed method:

``` php
->except($user)
->except($users)
```

Supported exclusion inputs should mirror recipient inputs where
sensible.

Typical example:

``` php
->recipients(IncidentRecipients::class)
->except($actor)
```

The package deduplicates before/after exclusions as needed.

------------------------------------------------------------------------

## 9. Recipient filters

Application/global filters are registered through the service
container/configuration rather than typically chained per notification.

Contract:

``` php
interface RecipientFilter
{
    public function filter(
        iterable $recipients,
        NotificationContext $context
    ): iterable;
}
```

Use cases:

``` text
active users only
tenant scoping
eligibility rules
soft-deleted exclusions
```

------------------------------------------------------------------------

## 10. Optional channels

Explicit override:

``` php
->channels([
    'push',
    'mail',
    'mqtt',
])
```

This controls the optional channels requested by the notification.

It does not include structural channels.

Structural:

``` text
database
broadcast
```

Optional:

``` text
push
mail
mqtt
custom channels
```

If `channels()` is omitted, configured defaults are used.

### Single-channel convenience

Proposed:

``` php
->channel('push')
```

This is ergonomic but not essential.

Recommendation: include it only if implementation remains unambiguous.

------------------------------------------------------------------------

## 11. Preferences

The builder does not directly manipulate recipient preferences.

Resolution remains internal:

``` text
system capability
+
event requested channels
+
effective user preference
=
recipient DeliveryPlan
```

Application code should not need:

``` php
->respectPreferences(true)
```

Preferences are always respected for preference-aware channels.

------------------------------------------------------------------------

## 12. Context delivery

### Broadcast convenience

``` php
->broadcastTo("incident.{$incident->id}")
```

Equivalent conceptually to:

``` php
->contextTo(
    ContextTarget::broadcast(
        "incident.{$incident->id}"
    )
)
```

### Generic context target

``` php
->contextTo(
    ContextTarget::mqtt(
        "incidents/{$incident->id}",
        qos: 1,
        retain: false,
    )
)
```

Multiple context targets are allowed:

``` php
->contextTo(ContextTarget::broadcast('incident.347'))
->contextTo(ContextTarget::mqtt('incidents/347'))
```

or:

``` php
->contextTargets([
    ContextTarget::broadcast('incident.347'),
    ContextTarget::mqtt('incidents/347'),
])
```

Exact plural convenience name is provisional.

------------------------------------------------------------------------

## 13. ContextTarget

Proposed factories:

``` php
ContextTarget::broadcast(
    string $channel
)

ContextTarget::mqtt(
    string $topic,
    int $qos = 1,
    bool $retain = false
)

ContextTarget::custom(
    string $transport,
    string $destination,
    array $options = []
)
```

This allows third-party context transports.

------------------------------------------------------------------------

## 14. send()

Primary terminal operation:

``` php
->send()
```

Accepted return type:

``` php
NotificationDispatchResult
```

Not `void`.

Rationale:

The caller may need:

``` text
notification ID
correlation ID
recipient count
planned recipient queue job count
context target count
```

Accepted result shape (ADR-0034):

``` php
final readonly class NotificationDispatchResult
{
    public function __construct(
        public string $notificationId,
        public string $correlationId,
        public int $recipientCount,
        public int $plannedQueueJobCount,
        public int $contextDeliveryCount,
    ) {}
}
```

This result reports orchestration acceptance/planning, not final
provider delivery.

The result is an immutable snapshot computed during `send()`:

| Field | Meaning |
| --- | --- |
| `notificationId` | Logical ID shared across recipients and context plans; not an inbox primary key. |
| `correlationId` | Operational correlation ID. |
| `recipientCount` | Final recipients after normalization, exclusions and filters. |
| `plannedQueueJobCount` | Recipient/channel jobs planned for asynchronous execution; excludes synchronous work, skips and context work. |
| `contextDeliveryCount` | Number of context delivery plans, not subscribers or successful publications. |

One recipient/channel job may handle multiple destinations without increasing
the job count. Two recipients with queued mail and push, synchronous database
persistence and no other channels produce four planned jobs.

The result does not change after commit and is not proof of persistence,
enqueue or delivery. A result returned before a rollback describes a discarded
plan. The previous `queuedDeliveryCount` field is replaced, without an alias.

See [ADR-0032](adr/0032-notification-identity.md) and
[ADR-0034](adr/0034-planning-and-after-commit-execution.md).

------------------------------------------------------------------------

## 15. plan()

Recommended internal/advanced operation:

``` php
->plan()
```

Returns:

``` php
NotificationDispatchPlan
```

Use cases:

``` text
testing
diagnostics
preview
advanced application orchestration
```

Important decision:

Recommendation is to expose `plan()` publicly during 0.x, but document
it as advanced API.

Example:

``` php
$plan = Notify::make(...)
    ->recipients(...)
    ->plan();
```

Then:

``` php
$executor->execute($plan);
```

Potential misuse risk exists, so this should remain advanced
documentation.

------------------------------------------------------------------------

## 16. dispatch() alias

Avoid adding both:

``` php
->send()
->dispatch()
```

as public terminal aliases in v0.1.

Recommendation:

Use only:

``` php
->send()
```

for fluent API.

Use `NotificationDispatcher::dispatch()` in explicit service API.

This keeps terminology clear.

------------------------------------------------------------------------

## 17. Explicit object API

Core services:

``` php
NotificationDispatcher
DeliveryPlanner
DeliveryExecutor
ChannelRegistry
PreferenceResolver
NotificationRepository
```

Primary advanced flow:

``` php
$context = new NotificationContext(
    type: 'incident.created',
    title: 'Nueva incidencia',
    message: 'Se reportó una incidencia.',
    severity: NotificationSeverity::INFO,
    occurredAt: now(),
    actor: $actor,
    subject: $incident,
    data: [],
    actions: [],
);

$result = $dispatcher->dispatch(
    context: $context,
    recipients: IncidentRecipients::class,
);
```

The exact dispatcher signature should be intentionally smaller than the
builder internals.

------------------------------------------------------------------------

## 18. NotificationContext

Proposed immutable object:

``` php
final readonly class NotificationContext
{
    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public NotificationSeverity $severity,
        public DateTimeImmutable $occurredAt,
        public ?ActorReference $actor,
        public ?SubjectReference $subject,
        public array $data,
        public array $actions,
    ) {}
}
```

Recommended construction through factory/builder rather than forcing
application developers to instantiate every normalized reference
manually.

------------------------------------------------------------------------

## 19. ActorReference

``` php
final readonly class ActorReference
{
    public function __construct(
        public string $id,
        public ?string $type = null,
        public ?string $display = null,
        public array $data = [],
    ) {}
}
```

The package should provide an `ActorResolver`/transformer for objects.

------------------------------------------------------------------------

## 20. SubjectReference

``` php
final readonly class SubjectReference
{
    public function __construct(
        public string $type,
        public string $id,
        public array $data = [],
    ) {}
}
```

Applications should be able to register morph aliases / custom subject
type resolvers.

Do not expose PHP class names in payloads by default.

------------------------------------------------------------------------

## 21. Notification type

`type` remains a string in the public API.

Example:

``` php
Notify::make('incident.created')
```

Applications may define their own enum:

``` php
enum NotificationType: string
{
    case INCIDENT_CREATED = 'incident.created';
}
```

Recommended builder support:

``` php
Notify::make(NotificationType::INCIDENT_CREATED)
```

using:

``` text
string|BackedEnum
```

internally normalized to string.

The package should not force a global notification-type enum because
types are application-owned.

------------------------------------------------------------------------

## 22. Error model

Builder validation errors:

``` text
MissingTitleException
MissingMessageException
MissingRecipientsException
InvalidNotificationTypeException
InvalidActionException
```

Infrastructure/configuration errors remain:

``` text
ChannelConfigurationException
ChannelNotFoundException
...
```

Delivery runtime failures do not generally throw back into the original
HTTP request once queued.

------------------------------------------------------------------------

## 23. Transaction behavior

Accepted behavior (ADR-0034): plan synchronously during `send()` and defer
execution until the outermost successful commit when a transaction is active
on the application's dispatch connection.

During `send()`: validate and normalize, allocate identities, resolve recipients
once, apply exclusions/filters/preferences, resolve destinations, build immutable
recipient/context plans and compute result counts. Planning writes no inbox or
tracking rows, enqueues nothing and publishes nothing.

After commit: persist the inbox when enabled, record tracking when enabled,
and execute/enqueue personal and context deliveries. Preserve persistence before
deliveries that reference a personal inbox ID. Rollback discards the work of its
transaction scope; an inner commit alone does not release it.

Recipients, preferences and destinations reflect the moment of `send()`.
Applications should call it after their relevant changes. Later changes do not
rebuild the plan, and execution/retries must not rerun recipient resolvers.
Normalized values and identities are snapshots, not live mutable model state.

Without an active transaction, execution follows planning immediately. The same
boundary applies with queueing disabled or a synchronous queue connection.
No public before-commit override or explicit after-commit method is required,
and no global application queue setting must be changed.

Planning errors surface during `send()`. Execution failures after commit cannot
roll back business data. The result is not a durable receipt: after-commit does
not close the crash window between commit and persistence/enqueue. An outbox or
distributed transaction guarantee is outside the initial scope.

------------------------------------------------------------------------

## 24. Queue behavior

The builder does not expose queue backend selection.

Application infrastructure owns:

``` text
database
redis
sqs
...
```

Potential per-notification queue hint:

``` php
->onQueue('critical-notifications')
```

Recommendation:

Do not include in initial public API unless required.

Prefer package config / channel-level queue configuration.

------------------------------------------------------------------------

## 25. Custom channels

Third-party channel registration is separate from notification usage.

Once registered:

``` php
->channels(['sms'])
```

works without builder changes.

The builder does not know provider implementation details.

------------------------------------------------------------------------

## 26. Custom context transports

Once registered:

``` php
->contextTo(
    ContextTarget::custom(
        transport: 'nats',
        destination: 'incidents.347'
    )
)
```

works without changing the builder.

------------------------------------------------------------------------

## 27. Static vs instance usage

Recommended public style:

``` php
Notify::make(...)
```

Internally, the facade resolves a `NotificationOrchestrator` service
from the container.

Applications can dependency-inject:

``` php
public function __construct(
    private NotificationOrchestrator $notifications
) {}
```

and use:

``` php
$this->notifications
    ->make(...)
    ->...
```

The facade is convenience, not architectural dependency.

------------------------------------------------------------------------

## 28. Recommended v0.1 minimal developer experience

### Simple

``` php
Notify::make('document.uploaded')
    ->title('Nuevo documento')
    ->message('Se cargó un nuevo documento.')
    ->recipients($users)
    ->send();
```

### With business resolver

``` php
Notify::make('incident.created')
    ->title('Nueva incidencia')
    ->message('Se reportó una nueva incidencia.')
    ->subject($incident)
    ->actor($user)
    ->recipients(IncidentRecipients::class)
    ->except($user)
    ->send();
```

### With actions

``` php
Notify::make('property.map.authorization_required')
    ->title('Mapa pendiente')
    ->message('Existe un mapa pendiente de autorización.')
    ->subject($property)
    ->recipients(MapAuthorizationRecipients::class)
    ->action(
        NotificationAction::navigate(
            id: 'view_map',
            label: 'Ver mapa',
            url: "/properties/{$property->id}/map",
            data: [
                'property_id' => $property->id,
            ],
        )
    )
    ->send();
```

### Notification + realtime context

``` php
Notify::make('incident.followup.created')
    ->title('Nuevo seguimiento')
    ->message('Se agregó un seguimiento.')
    ->subject($incident)
    ->recipients(IncidentRecipients::class)
    ->broadcastTo("incident.{$incident->id}")
    ->contextTo(
        ContextTarget::mqtt(
            "incidents/{$incident->id}"
        )
    )
    ->send();
```

------------------------------------------------------------------------

## 29. Public API freeze policy

During `0.x`:

``` text
method names may change
contracts may evolve
DTOs may change
```

At `1.0.0`, freeze:

``` text
Notify::make()
NotificationBuilder documented methods
RecipientResolver
Channel registration contract
ContextTarget
NotificationAction
NotificationDispatchResult
NotificationContext
payload schema 1.x compatibility policy
```

Internal classes remain non-public unless explicitly documented.

## Blade integration

When the Blade feature is enabled, the package exposes:

``` blade
<x-notifications::bell />
<x-notifications::inbox />
<x-notifications::toast-container />
```

These components are adapters over the package HTTP/realtime protocol.

See `BLADE-AND-FRONTEND.md`.
