# Channel Registry Specification

## Status

Draft v0.1 --- implementation-oriented design.

## Purpose

`ChannelRegistry` is the authoritative runtime catalog of delivery
channels known by the orchestrator.

It must answer:

-   Which channels exist?
-   Which are built-in vs extension channels?
-   Which are structural vs optional?
-   Which are enabled?
-   Which implementation class/driver handles them?
-   How is configuration validated?
-   How is health reported?
-   How are recipient destinations resolved?
-   Which capabilities does each channel expose?

## Core principle

The orchestrator core must not contain `switch` statements for known
channel names.

Bad:

``` php
switch ($channel) {
    case 'mail':
        ...
    case 'mqtt':
        ...
}
```

Required:

``` text
channel name
    ↓
ChannelRegistry
    ↓
ChannelDefinition
    ↓
registered implementation
```

This is what makes custom third-party channels possible without
modifying core code.

## Channel identity

A channel has a stable machine name:

``` text
database
broadcast
mail
push
mqtt
sms
teams
...
```

Names are lowercase strings and should match:

``` text
^[a-z][a-z0-9._-]*$
```

Package-reserved built-in names:

``` text
database
broadcast
mail
push
mqtt
```

Third-party packages must not override built-in channels unless an
explicit replacement API is later introduced.

## ChannelDefinition

The registry stores immutable channel metadata separately from runtime
channel instances.

Proposed value object:

``` php
final readonly class ChannelDefinition
{
    public function __construct(
        public string $name,
        public ChannelKind $kind,
        public string $driver,
        public bool $preferenceAware,
        public bool $requiresDestination,
        public bool $queueable,
        public bool $healthCheckable,
    ) {}
}
```

Potential initial definitions:

``` text
database
kind                structural
preferenceAware     false
requiresDestination false
queueable           true
healthCheckable     true

broadcast
kind                structural
preferenceAware     false
requiresDestination true
queueable           true
healthCheckable     true

mail
kind                optional
preferenceAware     true
requiresDestination true
queueable           true
healthCheckable     true

push
kind                optional
preferenceAware     true
requiresDestination true
queueable           true
healthCheckable     true

mqtt
kind                optional
preferenceAware     true
requiresDestination true
queueable           true
healthCheckable     true
```

## ChannelKind

``` php
enum ChannelKind: string
{
    case STRUCTURAL = 'structural';
    case OPTIONAL = 'optional';
}
```

`database` and `broadcast` are structural in v0.1.

## Channel capabilities

A future `ChannelCapability` enum may be introduced if simple flags
become insufficient.

Candidate capabilities:

``` text
multi_destination
supports_delivery_receipt
supports_provider_reference
supports_rich_actions
supports_redacted_payload
supports_batch_send
```

For v0.1, avoid premature complexity. Use explicit metadata only where
the planner needs it.

## Registry contract

Proposed contract:

``` php
interface ChannelRegistry
{
    public function register(
        ChannelDefinition $definition,
        string|NotificationChannel $implementation
    ): void;

    public function has(string $name): bool;

    public function get(string $name): RegisteredChannel;

    /** @return iterable<RegisteredChannel> */
    public function all(): iterable;

    /** @return iterable<RegisteredChannel> */
    public function enabled(): iterable;

    public function validateEnabled(): void;
}
```

Exact signatures may be refined during implementation, but
responsibilities are fixed.

## RegisteredChannel

`RegisteredChannel` combines definition and implementation.

``` php
final readonly class RegisteredChannel
{
    public function __construct(
        public ChannelDefinition $definition,
        public NotificationChannel $channel,
    ) {}
}
```

This prevents callers from repeatedly resolving service-container
bindings.

## NotificationChannel

Recommended decomposition:

``` php
interface NotificationChannel
{
    public function name(): string;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;

    public function send(
        ChannelDelivery $delivery
    ): DeliveryResult;
}
```

`send()` receives a fully planned delivery. It should not re-run
recipient resolution or preference logic.

## Destination resolution

A channel that requires a recipient endpoint uses a separate resolver.

``` php
interface ChannelDestinationResolver
{
    /** @return iterable<ChannelDestination> */
    public function resolve(
        object $recipient,
        NotificationContext $context
    ): iterable;
}
```

The channel implementation may depend on its resolver, but the planner
should receive destinations before execution where practical.

## ChannelDestination

Provider-neutral value object:

``` php
final readonly class ChannelDestination
{
    public function __construct(
        public string $value,
        public array $metadata = [],
    ) {}
}
```

Examples:

Mail:

``` text
value = user@example.com
metadata = {name: "User"}
```

Push:

``` text
value = FCM_DEVICE_TOKEN
metadata = {platform: "ios", device_id: "..."}
```

MQTT:

``` text
value = notifications/user/25
metadata = {qos: 1, retain: false}
```

Broadcast:

``` text
value = private notification channel name
```

`database` may use a special internal destination or declare
`requiresDestination=false`.

## Registration lifecycle

Activation comes only from `notification-orchestrator.features.<name>` for
built-ins and registered extensions. Module settings do not contain competing
activation switches. Register metadata without instantiating disabled providers;
validate enabled dependencies without automatically activating them. See
[ADR-0033](adr/0033-canonical-feature-configuration.md).

Recommended lifecycle:

``` text
Service provider register()
        ↓
register built-in definitions
        ↓
register application / third-party extensions
        ↓
container boot
        ↓
validate enabled channel configuration
        ↓
application ready
```

Validation should occur during application boot in contexts where doing
so is safe.

CLI commands that intentionally inspect broken configuration, especially
`notifications:status`, must be able to collect validation errors rather
than crashing before report generation.

This implies validation should support two modes:

``` text
strict
diagnostic
```

Strict mode: - normal application boot / dispatch path; - throws
configuration exceptions.

Diagnostic mode: - used by `notifications:status`; - captures failures
into status report.

## Built-in registration

Conceptual provider registration:

``` php
$registry->register(
    new ChannelDefinition(
        name: 'database',
        kind: ChannelKind::STRUCTURAL,
        driver: 'laravel-database',
        preferenceAware: false,
        requiresDestination: false,
        queueable: true,
        healthCheckable: true,
    ),
    DatabaseNotificationChannel::class,
);
```

Equivalent definitions exist for `broadcast`, `mail`, `push` and `mqtt`.

## Third-party extension

Example:

``` php
NotificationChannels::register(
    definition: new ChannelDefinition(
        name: 'sms',
        kind: ChannelKind::OPTIONAL,
        driver: 'twilio',
        preferenceAware: true,
        requiresDestination: true,
        queueable: true,
        healthCheckable: true,
    ),
    implementation: TwilioSmsChannel::class,
);
```

A registered custom channel automatically becomes eligible for:

``` text
configuration validation
notifications:status
DeliveryPlanner
DeliveryExecutor
delivery tracking
preferences
```

provided its metadata marks it preference-aware.

## Override policy

Initial rule:

``` text
duplicate channel registration
-> ChannelAlreadyRegisteredException
```

No silent replacement.

A future explicit:

``` php
replace(...)
```

API may be considered after v1 if there is a real extension use case.

## Registry exceptions

Initial taxonomy:

``` text
ChannelNotFoundException
ChannelAlreadyRegisteredException
ChannelConfigurationException
ChannelRegistrationException
```

Provider-specific configuration exceptions may extend
`ChannelConfigurationException`.
