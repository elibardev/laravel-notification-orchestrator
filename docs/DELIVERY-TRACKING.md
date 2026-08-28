# Delivery Tracking Specification

## Status

Draft v0.1 --- implementation-oriented design.

## Purpose

Delivery Tracking records the lifecycle of each planned delivery attempt
per:

``` text
notification
+ recipient
+ channel
+ destination
```

It is an optional feature module.

It must provide operational traceability without becoming the source of
truth for application notification read/unread state.

## Core distinction

Delivery tracking answers:

``` text
Was delivery attempted?
Was it queued?
Was it accepted/sent?
Was it confirmed delivered?
Did it fail?
Was it skipped?
```

It does NOT answer:

``` text
Did the user read the notification?
```

Read state remains part of persistent notification state.

## Relationship to execution model

Accepted execution granularity:

``` text
one queued delivery job
per recipient + channel
```

A channel may contain multiple destinations.

Example:

``` text
User 25 / push
    ├── iPhone token
    └── Android token
```

For tracking precision, the module should support one delivery record
per destination when a channel resolves multiple independently
deliverable endpoints.

Recommended identity granularity:

``` text
notification + recipient + channel + destination fingerprint
```

## Delivery lifecycle

Canonical lifecycle:

``` text
planned
   |
   v
queued
   |
   v
processing
   |
   +--> sent
   |      |
   |      +--> delivered   (only when provider can confirm)
   |
   +--> failed
```

Skipped deliveries do not enter the normal execution lifecycle:

``` text
planned decision
   |
   v
skipped
```

## DeliveryStatus

Proposed enum:

``` php
enum DeliveryStatus: string
{
    case PLANNED = 'planned';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
```

## Status semantics

### planned

The planner decided delivery should be attempted.

### queued

A Laravel queue job representing this delivery was successfully
enqueued.

### processing

A worker has begun executing the delivery.

### sent

The channel/provider accepted the delivery or the synchronous channel
completed its send action.

This does not necessarily prove end-recipient delivery.

### delivered

Use only when the channel/provider supplies a reliable delivery
confirmation.

Examples may include provider callbacks or acknowledgements.

Do not automatically promote `sent` to `delivered`.

### failed

Execution failed after an attempt.

Retries may later transition the delivery back through processing and
sent/delivered.

### skipped

The planner intentionally did not attempt delivery.

Examples:

``` text
user_preference
not_requested
disabled
no_destination
```

## Database table

Optional table:

``` text
{prefix}deliveries
```

Proposed logical schema:

``` text
id
notification_id
correlation_id

notifiable_type
notifiable_id

channel
driver
provider

destination_hash
destination_label nullable

status
skip_reason nullable

attempts
max_attempts nullable

provider_reference nullable

planned_at nullable
queued_at nullable
processing_at nullable
sent_at nullable
delivered_at nullable
failed_at nullable

last_error_code nullable
last_error_message nullable

metadata nullable/json

created_at
updated_at
```

`notification_id` is the shared logical ID, not the inbox primary key. There
is no foreign key from it to the notifications table. Tracking remains usable
when inbox persistence is disabled. Optional `metadata.stored_notification_id`
may identify the personal inbox row when one exists; it is not a required column.
Keep recipient identity in logical tracking keys and recipient-scoped cleanup.
See [ADR-0032](adr/0032-notification-identity.md).

## Destination privacy

Raw destination values should not be stored by default when they may
contain sensitive identifiers.

Examples:

``` text
email address
FCM device token
MQTT private topic
phone number
```

Use:

``` text
destination_hash
destination_label
```

Example:

``` text
destination_hash = SHA-256(normalized destination)
destination_label = "iPhone"
```

The raw destination remains in the queued delivery payload only as
required for execution.

## Keys and indexes

Recommended indexes:

``` text
(notification_id)
(correlation_id)
(notifiable_type, notifiable_id)
(channel, status)
(status, created_at)
(provider_reference)
```

Recommended uniqueness candidate:

``` text
notification_id
notifiable_type
notifiable_id
channel
destination_hash
```

Whether this is enforced as a database unique key depends on
retry/idempotency implementation.

## Attempts

`attempts` represents delivery attempts for the same logical delivery.

It must not create a new logical delivery row for every retry unless a
future audit mode explicitly requests attempt-history rows.

Initial design:

``` text
one delivery row
many retry attempts
```

Latest failure information is stored on the delivery row.

Detailed attempt history may be added later as a separate optional
table:

``` text
{prefix}delivery_attempts
```

Not required for v0.1.

## Retry behavior

Laravel Queue remains authoritative for retry scheduling and failed job
mechanics.

The tracking module mirrors relevant lifecycle state.

Example:

``` text
attempt 1
processing
    ↓
timeout
    ↓
failed
attempts = 1

Laravel retries

attempt 2
processing
    ↓
sent
attempts = 2
```

The final delivery row becomes:

``` text
status = sent
attempts = 2
last_error_code = previous timeout code (optional retention policy)
last_error_message = previous timeout message (optional)
```

A decision must be made during implementation whether successful final
delivery clears the last error fields or preserves them in metadata.

Recommended: clear active error fields on success and preserve
prior-attempt summary only when verbose tracking is enabled.

## Relationship to failed_jobs

`failed_jobs` and `{prefix}deliveries` serve different purposes.

``` text
failed_jobs
= Laravel queue infrastructure failure record

deliveries
= notification-domain delivery state
```

They may reference the same failure but must not be conflated.

A permanently failed delivery should generally result in:

``` text
delivery.status = failed
+
Laravel failed_jobs entry
```

when the queue backend uses Laravel's failed-job mechanism.

## Idempotency

A queued delivery job must carry a stable logical delivery identifier.

Recommended:

``` text
delivery_id
```

The channel must be able to detect/reject accidental duplicate execution
where practical.

Potential idempotency key:

``` text
notification_id
+ notifiable identity
+ channel
+ destination_hash
```

External providers may still be at-least-once.

The package must not promise exactly-once delivery.

## DeliveryTrackingRepository

Proposed contract:

``` php
interface DeliveryTrackingRepository
{
    public function createPlanned(
        PlannedDelivery $delivery
    ): DeliveryRecord;

    public function markQueued(
        string $deliveryId
    ): void;

    public function markProcessing(
        string $deliveryId,
        int $attempt
    ): void;

    public function markSent(
        string $deliveryId,
        ?string $providerReference = null,
        array $metadata = []
    ): void;

    public function markDelivered(
        string $deliveryId,
        ?string $providerReference = null,
        array $metadata = []
    ): void;

    public function markFailed(
        string $deliveryId,
        DeliveryFailure $failure
    ): void;

    public function markSkipped(
        PlannedDelivery $delivery,
        SkipReason $reason
    ): DeliveryRecord;
}
```

Exact signatures remain provisional during `0.x`.

## Tracking integration points

``` text
DeliveryPlanner during send()
    |
    +--> immutable decisions only; no tracking writes

DeliveryExecutor after commit (or immediately without a transaction)
    |
    +--> create planned/skipped records when tracking enabled
    +--> mark queued
    +--> mark processing

NotificationChannel
    |
    +--> result sent / failed

Provider callback/webhook
    |
    +--> delivered / failed update
```

## Provider callbacks

Some providers can report delivery asynchronously.

The architecture should allow a provider adapter to map callbacks to:

``` text
provider_reference
    ↓
delivery record
```

The core should expose an internal service for safe status transitions.

MQTT, mail and push providers will differ in what can be proven as
delivered.

## Status transition rules

Recommended allowed transitions:

``` text
planned -> queued
planned -> processing        (synchronous/nonqueued)
planned -> skipped

queued -> processing
processing -> sent
processing -> failed

failed -> queued             (retry scheduling)
failed -> processing         (worker retry)

sent -> delivered
sent -> failed               (provider asynchronous failure, if supported)
```

Invalid transitions should raise an internal domain exception or be
ignored idempotently when the same transition is repeated.

## DeliveryTransitionGuard

Proposed internal component:

``` php
interface DeliveryTransitionGuard
{
    public function assertAllowed(
        DeliveryStatus $from,
        DeliveryStatus $to
    ): void;
}
```

## Synchronous structural channels

Database persistence may complete synchronously.

Example:

``` text
planned -> processing -> sent
```

Whether database persistence is tracked by default should be
configurable because it can generate high-volume tracking rows.

Recommended defaults:

``` text
track structural database = false
track structural broadcast = false
track optional external channels = true
```

Tracking structural channels can be enabled for debugging or regulated
environments.

## Configuration

Proposed:

``` php
'delivery_tracking' => [
    'channels' => [
        'database' => false,
        'broadcast' => false,
        'mail' => true,
        'push' => true,
        'mqtt' => true,
    ],

    'record_skipped' => true,

    'retention_days' => 90,

    'store_destination_label' => true,

    'store_metadata' => true,
],
```

Custom channels inherit a sensible default:

Enable this module only through `features.delivery_tracking`. The per-channel
values above select tracking scope; they do not activate channels. See
[ADR-0033](adr/0033-canonical-feature-configuration.md).

``` text
optional channel -> tracked when tracking module enabled
structural channel -> not tracked unless configured
```

## Retention

Delivery tracking is operational/audit data and can grow rapidly.

The package should support retention pruning.

Potential command:

``` bash
php artisan notifications:prune-deliveries
```

The exact command remains proposed.

Initial policy:

``` text
retention_days = 90
```

Applications may increase or disable automatic pruning according to
audit requirements.

## Status command integration

`notifications:status` should report whether delivery tracking is
enabled and its basic storage health.

Example:

``` text
Delivery Tracking
✓ enabled
✓ table available
✓ retention: 90 days
```

It should not scan the full table during routine health checks.

## Query API

Potential repository/query service:

``` php
$tracking->forNotification($notificationId);
$tracking->forRecipient($user);
$tracking->failed();
$tracking->byCorrelationId($correlationId);
```

No public fluent query API is frozen for v0.1.

## Observability events

Potential lifecycle events:

``` text
DeliveryPlanned
DeliveryQueued
DeliveryProcessing
DeliverySent
DeliveryDelivered
DeliveryFailed
DeliverySkipped
```

These should be emitted after persistence of the corresponding state
when tracking is enabled.

When tracking is disabled, selected operational events may still be
emitted by the executor.

## Security

Tracking metadata must not leak credentials or raw secrets.

Never persist:

``` text
FCM credentials
SMTP passwords
MQTT passwords
bearer tokens
full raw provider request headers
```

Error messages must be sanitized before persistence.

## Non-goals for v0.1

-   exactly-once external delivery;
-   full provider event archive;
-   billing/event metering;
-   long-term analytics warehouse;
-   immutable compliance ledger;
-   automatic user-read inference from provider delivery receipts.
