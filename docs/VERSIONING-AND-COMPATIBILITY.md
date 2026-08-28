# Versioning and Backward Compatibility Policy

## Status

Draft v0.1 --- accepted architectural direction.

## Independent compatibility surfaces

The project has multiple versioned contracts:

``` text
Composer package/API
payload schema
HTTP API
realtime event protocol
database schema
frontend JS client
```

These evolve independently but must remain coordinated.

## Composer package

Use Semantic Versioning.

``` text
0.x
-> development line; public API may evolve

1.x
-> backward-compatible public API within major version

2.0
-> breaking public API changes
```

## PHP public API

At 1.0, the documented public surface includes:

``` text
Notify facade
NotificationBuilder documented methods
NotificationOrchestrator service
RecipientResolver
RecipientFilter
Channel registry extension contracts
ContextTarget
NotificationAction
NotificationDispatchResult
selected DTO/value objects explicitly documented as public
testing fake/assertions
```

Classes not documented as public are internal and may change within
minor versions.

Use `@internal` where appropriate.

## Payload schema

Independent field:

``` json
"schema": "1.0"
```

Compatibility policy:

``` text
1.x payload
-> additive backward-compatible fields permitted
-> existing field meaning/type cannot break

2.0 payload
-> breaking payload changes
```

Clients must ignore unknown additive fields.

## Realtime event schema

Realtime envelope also carries a schema version.

Event names:

``` text
notification.created
notification.read
notification.unread
notification.read_all
```

Within protocol 1.x:

-   fields may be added;
-   required existing fields keep meaning;
-   event names are stable.

Breaking event changes require protocol major change.

## HTTP API versioning

Initial routes need not include `/v1` in path.

Instead, protocol/schema is documented as version 1.

Reason:

Package is embedded in an application, not necessarily a public internet
API.

If future breaking HTTP API evolution is needed, options include:

``` text
route prefix versioning
Accept header versioning
parallel route sets
```

Decision can be revisited before 2.0.

## Database migrations

Migrations are forward-only.

Package updates must never silently destructively alter user data.

Breaking schema migrations require:

``` text
release notes
upgrade guide
backup recommendation when relevant
```

Changing configured table prefix after production deployment is an
application migration responsibility.

## Configuration keys

At 1.0, documented config keys are public contract.

Within 1.x:

``` text
new keys may be added with defaults
existing keys should not be renamed/removed
```

Deprecated keys:

``` text
warn/document
continue working for a defined transition period
```

## Frontend JS client

If distributed as part of Composer assets, it follows package
compatibility where practical.

If later published separately to npm, it receives independent SemVer.

The HTTP/realtime protocol remains the compatibility boundary between
backend and frontend.

## Blade components

At 1.0, documented component names/required props are public:

``` blade
<x-notifications::bell />
<x-notifications::inbox />
<x-notifications::toast-container />
```

Internal markup/CSS classes are not guaranteed unless explicitly
documented.

Published/customized views are application-owned after publishing and
may require manual upgrade review.

## Channel extensions

Extension contracts marked public follow SemVer.

Third-party channels must declare compatible package versions in
Composer.

## Deprecation policy

Before removing a public 1.x API:

1.  mark deprecated;
2.  document replacement;
3.  emit deprecation notice where appropriate;
4.  remove only in next major version.

## Security fixes

Security fixes may tighten validation or reject previously unsafe
behavior in a minor/patch release even if behavior changes, when
required to protect users.

Such changes must be documented.

## Release artifacts

Each release should maintain:

``` text
CHANGELOG.md
upgrade notes when needed
tagged Git release
Composer version
documentation matching the release
```

## Compatibility matrix

CI validates declared PHP/Laravel support.

Initial baseline:

``` text
Laravel ^12.0
PHP >= 8.2
```

Support for future Laravel major versions should be added only after
tests pass and compatibility is documented.

## Payload/client compatibility tests

The repository should maintain fixture-based tests for:

``` text
payload schema 1.0
realtime envelopes 1.0
HTTP resource shape
```

This protects mobile/older web clients from accidental breaking changes.

## Database compatibility tests

Test migrations and repositories against:

``` text
SQLite (test speed)
MySQL/MariaDB
PostgreSQL
```

where CI resources permit.

## Upgrade philosophy

Prefer:

``` text
additive evolution
explicit deprecation
migration safety
stable protocol
```

over frequent breaking redesigns after 1.0.
