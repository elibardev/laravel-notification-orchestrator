# ADR-0033 --- Canonical configuration and feature activation

Status: **Accepted**

Date: 2026-08-27

## Context

The configuration examples duplicate activation in `features.*` and
module-level `*.enabled`, and alternate between configuration namespaces.
Competing switches make validation and module registration ambiguous.

## Decision

Use one configuration file and namespace:

``` text
config/notification-orchestrator.php
notification-orchestrator.*
```

`features.<name>` is the sole activation switch for each capability.
Module sections define behavior, connections, drivers and policies, not
another activation switch. This applies to built-ins and registered extensions.
Core remains always enabled.

Remove module activation keys such as `push.enabled`, `queue.enabled` and
`delivery_tracking.enabled`. There is no precedence or compatibility alias
for those old draft keys. Report them as invalid configuration with a safe
message pointing to the canonical key, rather than silently ignoring them.

The rule does not prohibit `enabled` for another purpose: device records,
user preferences and the inbox retention policy retain their own semantics.
For example, `retention.notifications.enabled` authorizes pruning, not
activation of database persistence.

### Validation and dependencies

- A disabled feature does not require provider credentials or instantiate its provider.
- An enabled feature validates its configuration and required dependencies.
- Missing dependencies fail explicitly; do not activate other features silently.
- Push with an external destination resolver does not require managed devices.
- Normal operation uses strict validation; `notifications:status` collects errors
  in diagnostic mode without exposing secrets.
- Feature activation, requested channels and preferences remain separate decisions.

Registered extensions use the same activation/validation mechanism. Channel
metadata and implementations still resolve through their registries, not a
switch over built-in channel names.

### Configuration combination

Combine package defaults with application overrides using schema-aware rules:

1. Maps merge recursively; only supplied keys replace defaults.
2. Lists replace completely; never append or merge list indexes.
3. Explicit `false`, `null` and empty lists are preserved and then validated
   against the relevant setting's contract.
4. An empty map adds no overrides; use the schema to distinguish maps from lists.

Thus an API middleware override replaces the entire middleware list, while a
partial `features` override preserves other feature defaults. An empty requested
channel list means no optional channels; it does not restore defaults.

The framework's shallow default merge is not sufficient for this contract.
Keep the combination centralized and compatible with `config:cache`; use class
names for configurable resolvers, not closures in configuration files.

Route names, broadcast channel names, Artisan commands and existing
`NOTIFICATIONS_*` environment variables are not configuration namespaces and
are not renamed by this decision.

## Consequences

There is one answer to whether a feature is enabled. No released consumers
need compatibility aliases because the package is still pre-implementation.
This refines configuration and lifecycle specifications; it does not implement
or automatically enable the documented modules.

## Required tests

- Canonical switches alone control registration and activation.
- Old duplicate activation keys fail with actionable, redacted diagnostics.
- Disabled providers need no credentials; invalid enabled providers fail fast.
- Missing dependencies do not silently enable another feature.
- Partial maps preserve defaults; lists replace; false/null/empty values survive.
- Configuration behavior is equivalent with and without `config:cache`.

## Framework reference

[Laravel 12 package configuration](https://laravel.com/docs/12.x/packages#configuration)
documents configuration publishing, cache restrictions and the first-level
merge performed by `mergeConfigFrom`. Recursive map/list behavior is the
package's explicit combination contract.
