# Elibardev Laravel Notification Orchestrator

> Reusable, modular and extensible notification orchestration for
> Laravel applications.

## Status

**Phases 1–3 implemented; initial release 0.1.0 remains unpublished.**
The PHP API remains experimental on the 0.x line. Persistent inbox, queue execution,
tracking, HTTP/personal broadcast and optional Blade/JS are implemented.
Mail, Push/FCM, managed devices, MQTT, context transports and optional presence
are implemented. See [Phase 2](docs/PHASE-2.md) for installation/API and
[Phase 3](docs/PHASE-3.md) for external adapters, security and verification limits.

Accepted pre-implementation clarifications (2026-08-27):

- [Logical notification and recipient inbox identity](docs/adr/0032-notification-identity.md).
- [Canonical configuration and feature activation](docs/adr/0033-canonical-feature-configuration.md).
- [Planning during send and execution after commit](docs/adr/0034-planning-and-after-commit-execution.md).

These contracts are now covered by configuration, identity, planning and transaction
tests. See [the Phase 1 implementation guide](docs/PHASE-1.md) for exact public
imports and extension contracts. That guide records the historical Phase 1
boundary; the Phase 2/3 guides describe current operational behavior.

Implementation is organized into three reviewed phases:

1. Foundation and orchestration engine, proven with fakes.
2. Persistent inbox, personal clients and operational controls.
3. External delivery providers and full-package integration.

Each phase contains small tested increments and requires review before the next.
The first skeleton checkpoint remains mandatory. See the
[implementation plan](docs/IMPLEMENTATION-PLAN.md) for scope and acceptance gates.

## Development and verification

For installation into a consuming application from the private GitHub repository,
see [Private installation guide (Spanish)](docs/PRIVATE-INSTALLATION.md).
Repository configuration belongs in the consuming application's composer.json,
not in this package's composer.json.

From this checkout, with PHP 8.2, PDO SQLite and Composer 2 available:

```sh
herd composer install
herd composer check-platform-reqs
herd composer check
npm test
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for individual test, analysis and formatting
commands. Tests use SQLite in memory and controlled channels, without external
providers. Storage tests execute package migrations. `composer.lock` pins the development environment;
consuming applications resolve runtime dependencies independently.

When installed into a Laravel 12 application through a local Composer path
repository, the provider is discovered automatically. There is no published
release implied by this checkout. Publish the configuration from that application:

```sh
php artisan vendor:publish --tag=notification-orchestrator-config
```

Re-running without `--force` preserves the application's existing file. This
publishes configuration only and never runs migrations. Nested maps merge and
lists replace completely, including empty lists, with strict validation and
configuration-cache parity as required by ADR-0033.

`Notify`, `send()`, the injectable dispatcher, `Notify::fake()` and
`notifications:status` are implemented. Use the fake for application tests:

```php
use Elibardev\NotificationOrchestrator\Facades\Notify;

Notify::fake();
Notify::make('record.created')->title('New record')->message('Created.')
    ->recipients($user)->send();
Notify::assertSentTo($user, 'record.created');
```

Inside a transaction, assertions become true only after the outermost commit.
The result reports planning, not delivery. Normal execution persists the inbox
before dependent deliveries and schedules configured channels after commit.
Enabled invalid features fail fast; disabled providers need no credentials.
HTTP, optional Blade, install/prune and four conditional migrations are available.
Sanctum is not installed or required; the default middleware is web/auth with CSRF.
Automated tests use controlled external clients. Live SMTP/FCM/Mosquitto/Reverb
and database engines other than SQLite are not claimed as verified.

Progress and implementation decisions are recorded in [CHANGELOG.md](CHANGELOG.md).

## Purpose

Elibardev Laravel Notification Orchestrator is intended to provide a
reusable notification layer on top of Laravel's native notification,
queue and broadcasting capabilities.

The package does **not** replace Laravel Notifications, Laravel Queue or
Laravel Broadcasting. It orchestrates them and adds a reusable
application-level model for:

-   notification contexts and semantic notification types;
-   dynamic recipient resolution;
-   delivery policies;
-   persistent notifications;
-   realtime delivery;
-   read/unread synchronization;
-   user notification preferences;
-   device registration;
-   push notifications;
-   optional delivery tracking;
-   optional presence-aware behavior;
-   configurable HTTP API;
-   configurable database table names and prefixes.

The consuming application remains responsible for its own business
rules.

## Design principles

1.  **Laravel-native first.** Prefer framework abstractions over custom
    transports.
2.  **Core independent of infrastructure.** The core must not depend
    directly on Reverb, Redis, Firebase or a specific queue backend.
3.  **Features are opt-in.** Optional capabilities can be enabled or
    disabled through configuration.
4.  **Business rules remain outside the package.** Applications define
    who should receive a notification and under what conditions.
5.  **One semantic payload.** Database, broadcast and push
    representations derive from one notification context.
6.  **No duplicated notification classes unless specialization is
    required.**
7.  **Database naming is configurable.** Every package-owned table
    supports a configurable prefix and explicit table overrides.
8.  **Queue backend is application-owned.** Database, Redis/Valkey, SQS
    or another supported Laravel driver may be used.
9.  **Broadcast backend is application-owned.** Reverb is recommended
    for self-hosted deployments but is not a hard dependency.
10. **Public API stability is a first-class concern.**

## Package identity

Composer name:

``` text
elibardev/laravel-notification-orchestrator
```

PHP namespace:

``` text
Elibardev\NotificationOrchestrator
```

These identifiers are accepted for the initial development line.

## Documentation

-   [Architecture](docs/ARCHITECTURE.md)
-   [Domain model](docs/DOMAIN-MODEL.md)
-   [Configuration specification](docs/CONFIGURATION.md)
-   [Database design](docs/DATABASE.md)
-   [Public API design](docs/PUBLIC-API.md)
-   [Realtime design](docs/REALTIME.md)
-   [Push and devices](docs/PUSH-DEVICES.md)
-   [Security model](docs/SECURITY.md)
-   [Testing strategy](docs/TESTING.md)
-   [Roadmap](docs/ROADMAP.md)
-   [Architecture Decision Records](docs/adr/)

## Target compatibility

Initial minimum compatibility:

``` text
Laravel: ^12.0
PHP: >= 8.2
```

The exact upper compatibility matrix will be verified continuously
through CI and may expand without changing the minimum supported
baseline.

## License

The project uses the [MIT License](LICENSE). See ADR-0002.

## Author

Copyright © 2026 **elibardev**.

## Initial release

The first development release is planned as `0.1.0`. Public API
compatibility may evolve throughout the `0.x` series; API stability will
be explicitly frozen for `1.0.0`.

-   [Installation and feature
    lifecycle](docs/INSTALLATION-AND-LIFECYCLE.md)

-   [Blade and frontend integration](docs/BLADE-AND-FRONTEND.md)

-   [Public testing API](docs/PUBLIC-TESTING-API.md)

-   [Headless and custom frontend
    integration](docs/HEADLESS-AND-CUSTOM-FRONTEND.md)

-   [Security and authorization](docs/SECURITY-AUTHORIZATION.md)

-   [Retention and pruning](docs/RETENTION-AND-PRUNING.md)

-   [Observability and logging](docs/OBSERVABILITY.md)

-   [Versioning and backward
    compatibility](docs/VERSIONING-AND-COMPATIBILITY.md)
