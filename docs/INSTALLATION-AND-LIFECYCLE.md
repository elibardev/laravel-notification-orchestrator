# Installation, Configuration and Feature Lifecycle

## Status

Draft v0.1 --- accepted architectural direction.

All three phases implement auto-discovery, configuration validation, install,
status, prune and four conditional migrations. See [PHASE-2.md](PHASE-2.md) and
[PHASE-3.md](PHASE-3.md) for executable instructions and current defaults.
The initial release remains unpublished; use an application Composer path
repository until an actual release is available.

## Installation goal

A Laravel application should be able to adopt the package progressively.

Minimum installation:

``` bash
composer require elibardev/laravel-notification-orchestrator
php artisan notifications:install
php artisan migrate
```

The package uses Laravel package auto-discovery for its Service
Provider.

## Initial package provider

``` text
Elibardev\NotificationOrchestrator\NotificationOrchestratorServiceProvider
```

Responsibilities:

-   merge package configuration;
-   register core contracts;
-   register built-in channels;
-   register enabled feature modules;
-   register Artisan commands;
-   load package routes when API is enabled;
-   load Blade components/views when frontend Blade integration is
    enabled;
-   register migrations/publishing behavior;
-   validate enabled feature configuration in strict mode.

## notifications:install

Primary installation command:

``` bash
php artisan notifications:install
```

Objectives:

1.  publish configuration;
2.  publish/copy required migrations for enabled managed features;
3.  report enabled features;
4.  validate initial configuration;
5.  explain required external dependencies without silently installing
    services;
6.  remain safe to run more than once.

Example:

``` text
Elibardev Notification Orchestrator

Configuration
✓ config/notification-orchestrator.php published

Features
✓ database
✓ broadcast
✓ api
○ preferences disabled
○ devices disabled
○ push disabled
○ mqtt disabled
○ delivery tracking disabled

Migrations
✓ notifications migration published

Next:
php artisan migrate
php artisan notifications:status
```

## Idempotency

Re-running:

``` bash
php artisan notifications:install
```

must not overwrite modified configuration or existing migrations without
explicit user intent.

## Feature lifecycle

Features are declared in configuration.

The canonical file is `config/notification-orchestrator.php`, accessed through
`notification-orchestrator.*`. Only `features.<name>` activates a module;
module-level `enabled` duplicates are invalid and have no compatibility aliases.
Module settings configure behavior, not activation. Dependencies are validated
explicitly and never silently enabled.

Configuration overrides recursively merge schema-defined maps and replace
lists completely, preserving valid explicit false/null/empty values. See
[CONFIGURATION.md](CONFIGURATION.md) and
[ADR-0033](adr/0033-canonical-feature-configuration.md).

Example:

``` php
'features' => [
    'database' => true,
    'broadcast' => true,
    'preferences' => true,
    'devices' => false,
    'push' => false,
    'mqtt' => false,
    'delivery_tracking' => false,
    'api' => true,
    'blade' => true,
],
```

Each feature module provides:

``` text
registration
configuration validation
migrations/resources if required
status contribution
optional routes
optional frontend resources
```

## CapabilityRegistry

The runtime feature registry answers:

``` text
is feature enabled?
is feature registered?
is feature healthy?
what dependencies does it expose?
```

Proposed API:

``` php
Capabilities::enabled('push');
Capabilities::enabled('mqtt');
Capabilities::enabled('blade');
```

Application code should rarely need this directly.

## Enabling features later

Example: an application initially uses only database + broadcast.

Later:

``` php
'devices' => true,
'push' => true,
```

The workflow should be:

``` bash
php artisan notifications:install
php artisan migrate
php artisan notifications:status
```

The install command detects newly required resources and reports them.

The feature toggles in this example belong inside the `features` map; setting
provider parameters alone does not enable a feature.

It must not duplicate existing resources.

## Managed migrations

Feature-to-table relationship:

``` text
database
-> {prefix}notifications

preferences
-> {prefix}preferences

devices managed
-> {prefix}devices

delivery_tracking
-> {prefix}deliveries
```

Push by itself does not require a devices table when an external
destination resolver is configured.

MQTT by itself does not require a table.

## Database defaults

All package tables use the consuming application's default DB connection
unless an advanced override is explicitly supported/configured.

## Upgrade lifecycle

Composer updates must not automatically execute migrations.

Recommended upgrade workflow:

``` bash
composer update elibardev/laravel-notification-orchestrator
php artisan notifications:status
php artisan migrate
```

The package documentation must include upgrade notes for breaking or
schema-affecting changes.

## Commands

Initial required commands:

``` bash
php artisan notifications:install
php artisan notifications:status
```

Planned operational command:

``` bash
php artisan notifications:prune-deliveries
```

Avoid unnecessary command proliferation.

## Configuration validation

Normal application boot/dispatch:

``` text
strict validation
```

Status command:

``` text
diagnostic validation
```

Invalid enabled features fail explicitly.

Disabled features do not require provider configuration.

## Recommended minimal configuration

``` php
return [
    'features' => [
        'database' => true,
        'broadcast' => true,
        'api' => true,
        'blade' => true,
        'preferences' => false,
        'devices' => false,
        'push' => false,
        'mqtt' => false,
        'delivery_tracking' => false,
    ],

    'database' => [
        'connection' => null,
        'table_prefix' => 'notify_',
    ],
];
```

This should be enough to provide:

``` text
persistent inbox
read/unread
unread counter
personal realtime synchronization
Blade notification UI
```

without configuring optional external providers.

## Frontend mode

Blade is optional:

``` php
'features' => [
    'blade' => false,
],
```

Applications may still use the complete backend and client protocol.

No package views/assets should be required when Blade integration is
disabled.

See `HEADLESS-AND-CUSTOM-FRONTEND.md`.
