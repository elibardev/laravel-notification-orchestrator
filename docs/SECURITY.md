# Security Model

## Trust boundaries

The package operates inside a Laravel application and inherits its authentication and authorization model.

The orchestrator must not assume that receiving a notification grants access to its referenced resource.

Example:

```text
Notification contains /incidents/347
```

The application must still authorize access when the user opens `/incidents/347`.

## Recipient resolution

Resolvers execute server-side and must return only authorized intended recipients.

The package may deduplicate and filter recipients but cannot infer application authorization.

## Broadcast

Personal notification channels must be private/authenticated.

Presence and context channels must be authorized by the consuming application.

## Push

Push payloads should minimize confidential information because lock-screen presentation may expose content.

Applications should be able to configure redacted push representations.

Example:

```text
Database:
"Property 678 has a cadastral discrepancy requiring review."

Push:
"You have a new review request."
```

## Device tokens

Device tokens must be treated as credentials-like identifiers.

Requirements before implementation:

- do not log tokens;
- mask tokens in debugging output;
- define encryption-at-rest option;
- revoke invalid tokens;
- restrict device management endpoints to the authenticated owner/admin policy.

## API

Package API routes must:

- default to authenticated middleware;
- support middleware customization;
- enforce ownership for notification read/unread operations;
- use rate limiting where appropriate;
- never trust client-supplied notifiable identifiers.

## URLs/actions

Notification actions are hints for the client, not authorization grants.

## Multi-tenant applications

The package must not assume a tenancy implementation.

Resolvers and policies remain responsible for tenant scoping.

Future versions may expose a scope resolver contract if required.
