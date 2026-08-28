# Testing Strategy

## Current coverage — Phases 1–3 (2026-08-28)

Herd PHP 8.2.31 / Laravel 12.68.0: **93 PHPUnit tests, 464 assertions**.
Coverage includes the original engine plus all four migrations, inbox/read state,
actual database queue workers/retries, tracking, HTTP/CSRF/ownership, personal
broadcast auth, optional frontend, Mail, FCM HTTP and Google Auth JWT signing,
managed-device encryption/rotation/reassignment/invalidation, MQTT ACK/timeout/TLS,
context transports, presence, pruning and combined 150-recipient execution.

Run `herd composer check` (validation, tests, PHPStan level 8, Pint) and `npm test`
(six framework-neutral client tests). Run `herd composer check-platform-reqs` and
`herd composer audit` for local dependency checks. Current CI targets **PHP 8.2**;
historical PHP 8.3 checks below are not a claim about this phase.

Browser evidence and the local Laravel fixture are documented in [PHASE-2.md](PHASE-2.md).
External clients are controlled test doubles; Google Auth signs real disposable
test JWTs but its HTTP exchange is simulated. SQLite is the verified database.
Other SQL engines and live SMTP/FCM/Mosquitto/Reverb were not exercised.
GitHub Actions is now active for the private repository. Its first run on Linux
PHP 8.2.33 passed all 93 PHP tests and PHPStan, but found seven style issues that
the local Pint cache had missed. These were reproduced with a fresh cache and
corrected without changing behavior. Check [GitHub Actions](https://github.com/elibardev/laravel-notification-orchestrator/actions)
for the result of each commit; do not infer a remote pass from local checks.
See [PHASE-3.md](PHASE-3.md) for separate opt-in live profiles and deployment limits.
No live credentials or test keys are committed.
Final `herd composer audit --locked` completed without vulnerability advisories
after retrying an initial Packagist DNS failure. Platform requirements passed.

## Historical coverage — complete Phase 1

The suite has **55 tests and 183 assertions** on Laravel 12.68.0, using PHPUnit 11
and Orchestra Testbench 10. It covers the skeleton plus configuration map/list
combination and cache parity, payload/defaults and safe references, recipient
composition/exclusions/filters, preference hierarchy, registry extensions and
errors, destination and job counts, context planning, all public fake assertions,
safe status diagnostics and native commit/rollback behavior.

Transaction cases include nested commits, inner/outer rollbacks, committed children
of rolled-back scopes, an unrelated connection, sync/disabled queue modes,
immutable result/recipient/destination snapshots, fake reset isolation and errors
after commit that cannot undo application data. Tests use PDO SQLite, without
RefreshDatabase wrapping these transaction cases. The only SQL table created by
the suite is a test-owned business-data fixture, not a package migration.

### Phase 1 verification (2026-08-27)

- PHP 8.3.30: `composer check` passed (Composer validation, 55 tests / 183
  assertions, PHPStan level 8, Pint).
- PHP 8.2.28: the same 55 tests / 183 assertions passed in randomized order with
  seed 827. PDO SQLite was enabled for that process only via
  `php -d extension=pdo_sqlite vendor/bin/phpunit --order-by=random --random-order-seed=827`.
  No system PHP configuration was modified.
- PHPStan and Pint passed on both PHP 8.2 and 8.3 using the configured scripts. No baselines,
  ignored errors, skipped tests or external notification services are required.
- Composer lock refresh installed no additional packages and reported no known
  vulnerability advisories.
- CI remains configured for PHP 8.2/8.3/8.4; remote CI and PHP 8.4 have not been
  verified locally. No live FCM, MQTT, mail or Reverb verification is claimed.

Use `composer test`, `composer analyse`, `composer format:check`, or the combined
`composer check`. Enable PDO SQLite in the development PHP runtime. Laravel's
application database and external provider infrastructure are not required.
See [PHASE-1.md](PHASE-1.md) for implementation decisions and extension examples.

## Historical coverage — checkpoint 1.1

The initial skeleton suite used PHPUnit 11 with Orchestra Testbench 10 and Laravel
12. It contained **6 tests and 28 assertions**, covering:

- Laravel boot and provider registration.
- Canonical declarative configuration loading without optional providers.
- Preservation of an application's complete top-level configuration override.
- Actual `vendor:publish` execution and preservation of an existing edited file.
- Actual `config:cache` execution and loading cached configuration in Laravel.
- Laravel discovery/boot from the package's Composer metadata, without manually
  listing the provider in the discovery test's Testbench providers.

Run `composer check` for Composer validation, the complete suite, PHPStan level 8
and Pint's non-mutating check. Run `composer format` to apply style fixes.
Temporary fixtures are isolated under `.cache/tests`; cleanup verifies that the
resolved target stays inside that directory. The suite needs no external service
or persistent application database.

### Local verification (2026-08-27)

- PHP 8.2.28 and PHP 8.3.30: `composer check` passed on both; 6 tests / 28 assertions
  per run, no PHPStan errors, Pint passed and Composer metadata/lock validated.
- Dependency installation and `composer check-platform-reqs` passed on PHP 8.2.
- Composer's installation audit reported no known vulnerability advisories.
- GitHub Actions is configured for PHP 8.2, 8.3 and 8.4 on Laravel 12. Workflow
  YAML was parsed locally, but no remote CI run is claimed; this checkout has no
  Git repository or remote yet. PHP 8.4 is not locally verified.

The strategy below describes the accepted test layers. The current coverage at
the top supersedes historical checkpoint limits; live verification remains separate.

## Framework

Use PHPUnit or Pest with Orchestra Testbench to execute the package in a
realistic Laravel environment.

## Test layers

### Unit tests

Cover:

-   context validation;
-   table name resolution;
-   recipient normalization;
-   deduplication;
-   exclusions;
-   channel policy;
-   feature capability registry;
-   payload transformation.

### Integration tests

Cover:

-   service provider boot;
-   configuration publishing;
-   migrations;
-   database notification persistence;
-   queue dispatch;
-   broadcast notification dispatch;
-   read/unread API;
-   device management;
-   preferences.

### Contract tests

Each optional driver should have a reusable contract test suite.

Example:

``` text
PushDriverContractTest
    FcmPushDriverTest
    FakePushDriverTest
```

### Compatibility matrix

CI should eventually test:

-   supported PHP versions;
-   supported Laravel versions;
-   SQLite for fast package tests;
-   MySQL/MariaDB integration;
-   PostgreSQL integration.

## Required test characteristics

-   no external network calls in the default suite;
-   fake queue/broadcast/push adapters;
-   deterministic clocks;
-   deterministic UUID/ULID generation where required;
-   feature-enabled and feature-disabled scenarios;
-   custom table-prefix scenarios;
-   explicit table override scenarios.

## Accepted decision regression coverage

Before the affected modules are complete, cover:

- ADR-0032: shared logical ID, distinct inbox IDs, independent read state,
  retry identity, tracking without inbox persistence and recipient-scoped pruning.
- ADR-0033: canonical feature activation, rejected duplicate switches, disabled
  providers without credentials, strict/diagnostic validation, dependency errors,
  recursive map overrides, full list replacement and configuration cache parity.
- ADR-0034: planning once during `send()`, counts independent of device count,
  no pre-commit writes/enqueue/publications, nested commit/rollback, sync/no-queue
  paths and fake recording at the execution boundary. Later application changes
  must not mutate the result or silently rebuild recipient/preference plans.

The Phase 1 suite covers the planning, configuration and transaction portions.
Phases 2 and 3 add persisted read isolation and managed-device execution checks;
see the current coverage above for evidence and unverified environments.

## Implementation phase gates

The three-phase [implementation plan](IMPLEMENTATION-PLAN.md) defines when each
test group becomes mandatory:

| Phase | Required evidence |
| --- | --- |
| 1 | Skeleton smoke tests, configuration, semantic payload/IDs, recipients, registries, planning, public fake and transaction boundaries. |
| 2 | Migrations, real database queue execution with controlled channels, inbox/preferences/tracking, API ownership, personal realtime, browser synchronization, install/prune and redaction. |
| 3 | Mail/FCM/MQTT adapters, managed/external devices, token security, context transports, optional presence, feature combinations and full compatibility/security regression. |

Run relevant tests per increment and all available regression tests, style and
static analysis before phase closure. Preserve the skeleton checkpoint before
engine implementation. Do not postpone ownership, encryption or transaction
tests until the final phase.

Use fakes for default provider tests, but exercise actual database queue
enqueue/worker behavior in Phase 2. Keep live provider tests in separate opt-in
profiles with safe destinations and approved credentials. Report unsupported or
untested environments honestly; passing fake tests is not live-provider evidence.

## Quality gates before v1.0

Candidate gates:

``` text
100% tests passing
static analysis at agreed PHPStan/Larastan level
coding standard enforcement
minimum meaningful coverage threshold
backward-compatibility review of public API
```

## Public consumer testing API

The package exposes `Notify::fake()` and orchestration assertions for
application tests.

See `PUBLIC-TESTING-API.md`.

This is distinct from the package's internal Orchestra Testbench suite.
