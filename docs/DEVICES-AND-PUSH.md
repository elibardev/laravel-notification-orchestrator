# Devices and Push Specification

## Status

Draft v0.1 --- implementation-oriented design.

Implemented in Phase 3. See [PHASE-3.md](PHASE-3.md) for exact namespaces,
signatures, HTTP paths, encrypted storage and FCM projection. Provisional method
sketches below are design context; ADR-0036 and the implementation guide take precedence.

## Principle

The package does not own users, authentication, sessions, or
authentication tokens.

``` text
user != session != push endpoint
```

The application remains authoritative for identity/authentication.

Push only needs to answer:

> Which valid push destinations currently belong to this notifiable?

## Push and Devices are separate modules

`push` is the delivery capability.

`devices` is an optional implementation for storing/managing push
endpoints.

Therefore all of these are valid:

``` text
devices managed + push FCM
devices external + push FCM
devices disabled + custom push resolver
push disabled
```

## Notifiable ownership

No FK to `users.id` is required.

Use:

``` text
notifiable_type
notifiable_id
```

This supports User, Employee, Account, Admin, or any application-defined
notifiable.

## Managed devices table

Logical table:

``` text
{prefix}devices
```

Proposed schema:

``` text
id

notifiable_type
notifiable_id

driver
platform

device_identifier nullable

token
token_hash

name nullable
enabled

last_used_at nullable
invalidated_at nullable

metadata nullable/json

created_at
updated_at
```

## Token storage

The raw push token must be recoverable for provider delivery, therefore
it cannot be stored only as a hash.

Recommended:

``` text
token
-> encrypted at rest using Laravel encrypted cast / Crypt

token_hash
-> SHA-256 fingerprint for lookup and deduplication
```

Do not use the encrypted token field for equality lookup.

## Device identifier

`device_identifier` is an application-installation identifier distinct
from the provider token.

Example:

``` text
installation abc123

FCM token today: AAA
FCM token later: BBB

same device_identifier
```

This allows token rotation without treating the same installation as a
completely new logical device.

The mobile application should generate and persist a random installation
UUID. It must not use hardware serial numbers or invasive hardware
identifiers.

## Token uniqueness

Recommended invariant:

``` text
driver + token_hash
```

is unique within the managed device store.

If a token previously associated with one user is registered by another
authenticated user, ownership may be reassigned according to the
registration policy.

This prevents a shared device from continuing to receive the prior
user's notifications.

## Registration API

When managed devices are enabled, proposed endpoints:

``` text
POST   /notification-devices
PATCH  /notification-devices/{device}
DELETE /notification-devices/{device}
```

Registration derives the notifiable from the authenticated request.

The client must not provide arbitrary `notifiable_id`.

Example:

``` json
{
  "driver": "fcm",
  "platform": "ios",
  "device_identifier": "01K...",
  "token": "FCM_TOKEN",
  "name": "iPhone"
}
```

Registration is effectively an upsert.

## Logout

Authentication logout and push deregistration are separate concepts.

Applications may choose to deregister/disable the current push device
during logout.

The package should document a secure default integration pattern but not
couple itself to Sanctum, Passport, session cookies, or another
authentication system.

## Invalid token handling

Permanent provider responses such as an unregistered FCM token should
invalidate the managed destination.

Conceptual flow:

``` text
FCM permanent invalid-destination response
        ↓
PushResult::invalidDestination
        ↓
device.enabled = false
device.invalidated_at = now()
```

Temporary provider failures do not invalidate the destination.

## PushDestination

``` php
final readonly class PushDestination
{
    public function __construct(
        public string $token,
        public string $driver,
        public ?string $platform = null,
        public ?string $deviceId = null,
        public ?string $label = null,
        public array $metadata = [],
    ) {}
}
```

## PushDestinationResolver

``` php
interface PushDestinationResolver
{
    /** @return iterable<PushDestination> */
    public function resolve(
        object $notifiable,
        NotificationContext $context
    ): iterable;
}
```

Default managed implementation:

``` text
DatabasePushDestinationResolver
```

Applications may replace it with a custom resolver over their own
tables/services.

## PushDriver

`PushChannel` should not contain FCM-specific logic.

``` php
interface PushDriver
{
    public function send(
        PushDestination $destination,
        NotificationPayload $payload
    ): PushResult;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;
}
```

Initial reference driver:

``` text
fcm
```

Future drivers can be added without changing DeliveryPlanner.

## Push tap

When persistence is enabled, the personal push projection includes the
recipient-specific persistent inbox ID as `notification_id`, plus normalized
actions. That ID is not the shared logical semantic payload ID. Without inbox
persistence, omit `notification_id` and skip mark-read; push remains usable.
See [ADR-0032](adr/0032-notification-identity.md).

On tap:

``` text
mobile receives push
    ↓
opens/deep-links target
    +
authenticated markRead(notification_id)
    ↓
database becomes authoritative
    ↓
personal synchronization updates other devices
```

Displaying a push is never itself a read event.

Destinations are selected during `send()` planning. Execution and retries do not
rerun recipient resolution; a provider may still reject a destination that
became invalid. Planning does not publish or write tracking before commit.

Before sending to a managed endpoint, verify that its ownership still matches
the intended recipient and that it is not disabled/invalidated. Fail safely
without provider delivery if it was reassigned; do not rerun recipient resolution
or redirect the notification to a new owner. This protects the security boundary
without changing the plan's original membership or counts (ADR-0034).
