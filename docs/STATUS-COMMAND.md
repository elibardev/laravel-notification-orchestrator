# notifications:status Command

## Status

Accepted design requirement.

The registry-based command checks implemented channels/context transports,
required tables, queue infrastructure, device encryption and presence configuration.
It does not contact external providers: HEALTHY means local configuration readiness,
not successful remote delivery. Exit codes are 0 healthy, 1 invalid, 2 degraded.
See [PHASE-3.md](PHASE-3.md) for current adapter checks and separate live profiles.

## Command

``` bash
php artisan notifications:status
```

The command is the standard operational diagnostic entry point for
Elibardev Laravel Notification Orchestrator.

## Objectives

The command should report:

-   package version;
-   Laravel and PHP runtime;
-   package configuration validity;
-   database availability;
-   queue configuration;
-   enabled structural channels;
-   enabled optional channels;
-   configured providers/drivers;
-   provider health where meaningful;
-   overall health classification.

## Health states

``` text
HEALTHY
DEGRADED
INVALID
```

### HEALTHY

Configuration is valid and enabled provider health checks succeed.

### DEGRADED

Configuration is valid but one or more runtime dependencies are
currently unavailable.

Example:

``` text
MQTT broker configured correctly
but broker connection times out
```

### INVALID

An enabled feature/channel has invalid or incomplete configuration.

Example:

``` text
MQTT enabled but broker host missing
```

## Example healthy output

``` text
Elibardev Notification Orchestrator
Version: 0.1.0
Laravel: 12.x
PHP: 8.2+

Configuration
✓ Configuration valid
✓ Database connection
✓ Queue: database

Structural Channels
✓ database
✓ broadcast        reverb

Optional Channels
✓ push             fcm
✓ mail             smtp
✓ mqtt             mosquitto

Services
✓ Reverb configured
✓ FCM credentials loaded
✓ MQTT configuration valid
✓ MQTT broker reachable

Overall status
✓ HEALTHY
```

## Example degraded output

``` text
Optional Channels
✓ push             fcm
✓ mail             smtp
! mqtt             mosquitto

MQTT
! Broker unreachable
  Host: mqtt.internal
  Port: 1883
  Error: Connection timed out

Overall status
! DEGRADED
```

## Example invalid output

``` text
Optional Channels
✗ mqtt             mosquitto

MQTT
✗ MQTT is enabled but broker host is not configured.
  Missing configuration:
  notification-orchestrator.mqtt.host

Overall status
✗ INVALID
```

## Extensibility

The command must not contain provider-specific health logic.

Each registered channel contributes health information through its
channel/health contract.

Conceptual flow:

``` text
notifications:status
        |
        v
ChannelRegistry
        |
        +-- database.health()
        +-- broadcast.health()
        +-- push.health()
        +-- mail.health()
        +-- mqtt.health()
        +-- custom channels...
        |
        v
StatusReport
```

This ensures third-party registered channels automatically participate
in diagnostics.

## Future options

Potential options, not required for initial v0.1:

``` bash
php artisan notifications:status --channel=mqtt
php artisan notifications:status --verbose
```

The base command is required from the initial package implementation.

## Devices and context transports

When enabled, status should include:

``` text
Push
✓ destination resolver: managed
✓ FCM configuration

Managed Devices
✓ table available
✓ token encryption available

Context Transports
✓ broadcast / reverb
✓ mqtt / mosquitto
```

For external device resolution, status reports the configured resolver
without assuming a package-owned device table.

## Security-safe output

Activation is read only from `notification-orchestrator.features.*`.
Obsolete draft module switches such as `push.enabled` are configuration
errors, not alternate activation sources. Diagnostic mode must report them
and missing dependencies without failing before the report can be rendered.
Never instantiate disabled providers just to validate their credentials.
See [ADR-0033](adr/0033-canonical-feature-configuration.md).

Status output may report provider names, hostnames and ports when
useful, but must redact credentials/tokens.

## Retention status

Report configured retention without scanning large tables.

``` text
Retention
notifications: disabled
deliveries: 90 days
invalid devices: 30 days
```

## Versioning status

Verbose status may include:

``` text
Package version
Payload schema version
Realtime protocol version
```
