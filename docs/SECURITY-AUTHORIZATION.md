# Security and Authorization Specification

## Status

Draft v0.1 --- accepted architectural direction.

## Security principles

1.  The package never treats a notification as an authorization grant.
2.  Authentication is application-owned.
3.  Authorization of referenced domain resources is application-owned.
4.  Personal notification state can only be read or mutated by its
    owning notifiable.
5.  Push/MQTT/Broadcast destinations are transport addresses, not
    identity authorities.
6.  Sensitive data must be minimized in externally visible payloads.
7.  Secrets and raw provider credentials must never be persisted in
    delivery tracking or logs.
8.  Package defaults should fail closed where ambiguity exists.

## Authentication boundary

The package does not mandate:

``` text
Laravel session auth
Sanctum
Passport
JWT
custom guards
```

HTTP route middleware is configurable.

Example:

``` php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
]
```

The package resolves the authenticated notifiable through a configurable
resolver.

## Authenticated notifiable resolver

Proposed contract:

``` php
interface AuthenticatedNotifiableResolver
{
    public function resolve(Request $request): object;
}
```

Default implementation may use:

``` php
$request->user()
```

Applications with custom identity models may replace it.

## Notification ownership

Every notification operation must be scoped to:

``` text
notifiable_type
notifiable_id
```

Operations include:

``` text
list
bootstrap
unread count
mark read
mark unread
read all
managed device operations
preference operations
```

A client must never submit an arbitrary owner identifier for these
actions.

## Domain authorization

Example notification:

``` json
{
  "action": {
    "id": "view_incident",
    "type": "navigate",
    "url": "/incidents/347"
  }
}
```

Opening `/incidents/347` still executes the application's normal
policy/authorization.

The presence of the action does not imply access.

## Action security

### navigate

A navigate action is a client hint only.

### command

A command action must be resolved by application code.

The package does not automatically execute arbitrary URLs, HTTP methods,
controller names or serialized callbacks from notification payloads.

For v0.1:

``` text
command action
-> action.id
-> application-registered handler
-> authenticated application endpoint/service
-> application authorization
```

## Payload minimization

Push and MQTT may expose content outside the primary web application.

Applications should be able to provide redacted channel representations.

Recommended future contract:

``` php
interface ChannelPayloadTransformer
{
    public function transform(
        NotificationPayload $payload,
        string $channel
    ): NotificationPayload;
}
```

Initial built-in policy:

-   database/broadcast may use full normalized payload;
-   push should support reduced body/data;
-   MQTT can use full or transformed payload according to channel
    policy.

No automatic sensitive-domain detection is attempted.

## Personal broadcast authorization

Personal notification channels must be private/authenticated.

The package owns the personal channel authorization convention.

It must verify that the authenticated identity matches the requested
notifiable destination.

## Context broadcast authorization

Application-owned.

Example:

``` text
incident.347
property.678
```

The application must define who may subscribe.

The orchestrator must not infer context authorization from recipient
notification rules.

## MQTT security

MQTT deployments must support:

``` text
client authentication
publish authorization
subscribe authorization
TLS where appropriate
topic ACLs
```

The Laravel/server publisher should have publish permissions only as
required.

End clients should have subscribe permissions only for their authorized
personal/context topics.

Topic names alone are not authorization.

## MQTT topic identifiers

Avoid exposing:

``` text
PHP namespaces
database secrets
raw emails
session tokens
provider tokens
```

Recommended use of opaque or application-safe identifiers.

Example:

``` text
notifications/users/01K...
incidents/01K...
```

Applications using sequential IDs should evaluate enumeration risk and
broker ACL design.

## Push security

Managed device registration:

``` text
authenticated owner
-> register/update own endpoint
```

The request does not accept arbitrary `notifiable_id`.

Raw tokens:

``` text
encrypted at rest
never logged
never returned through list APIs unless strictly required
```

Token hash may be used for lookup/debug fingerprinting.

## Device reassignment

If the same provider token is registered by a new authenticated
notifiable, managed storage may reassign ownership.

The operation should emit an audit/lifecycle event.

## Preferences security

Users can change only their own preferences through the standard API.

Application administrator interfaces, if any, are application-owned.

Structural channels:

``` text
database
broadcast
```

cannot be disabled by user preferences.

## CSRF and API clients

Middleware configuration determines CSRF/session behavior.

Blade/session applications should use Laravel's normal CSRF protections.

Token-authenticated APIs should use the application's API authentication
controls.

## Rate limiting

Recommended default route groups should allow configurable rate
limiting.

Sensitive mutation endpoints:

``` text
device registration
preference changes
read/unread bulk operations
```

should support Laravel throttling middleware.

## Logging

Security-sensitive values must be redacted.

Never log:

``` text
FCM/APNs token
SMTP password
MQTT password
OAuth token
session token
authorization header
raw encrypted secret
```

## Error handling

Public API errors should not expose provider secrets or internal
stack/config paths beyond safe diagnostic messages.

`notifications:status --verbose` may expose hostnames/ports but never
passwords or credentials.

## Multi-tenancy

The package is tenancy-neutral.

Applications must enforce tenant isolation in:

``` text
recipient resolvers
authenticated notifiable resolver
context authorization
custom destination resolvers
```

The package should allow tenant-aware filters/resolvers but not impose a
tenancy library.

## Security testing requirements

Mandatory tests:

``` text
cannot read another user's notification
cannot mark another user's notification read
cannot alter another user's preferences
cannot register device for arbitrary user
personal broadcast authorization is identity-scoped
action does not bypass domain policy
tokens are encrypted in storage
tokens absent from logs/tracking
MQTT config redacts credentials
```
