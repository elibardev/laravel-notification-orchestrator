# Database Design

## Principles

1.  Package-owned table names are resolved centrally.
2.  All table names support a global configurable prefix.
3.  Explicit table names override the prefix-derived names.
4.  Database schema must be portable across MySQL/MariaDB and PostgreSQL
    where practical.
5.  The package should avoid DB-specific features in the core schema
    unless guarded by drivers.
6.  Foreign keys to application users should not be assumed because
    notifiable models may vary.

## 1. Notifications

Prefer compatibility with Laravel database notifications.

Logical schema:

``` text
{prefix}notifications

id                string/uuid
type              string
notifiable_type   string
notifiable_id     string-compatible
data              json/text
read_at           nullable timestamp
created_at        timestamp
updated_at        timestamp
```

Index requirements:

``` text
(notifiable_type, notifiable_id)
(notifiable_type, notifiable_id, read_at)
created_at
```

Exact Laravel migration compatibility must be validated during
implementation.

`id` is the recipient-specific `storedNotificationId`, allocated during
planning and persisted only at execution. Each recipient has a distinct row.
The semantic payload in JSON `data` retains the shared logical ID at `data.id`.
No dispatch table or additional logical-ID column is required initially.
See [ADR-0032](adr/0032-notification-identity.md).

## 2. Preferences

Optional.

``` text
{prefix}preferences

id
notifiable_type
notifiable_id
notification_type
channel
enabled
created_at
updated_at
```

Unique logical key:

``` text
notifiable_type
notifiable_id
notification_type
channel
```

Potential future scope support can be added through a versioned
migration.

## 3. Devices

Optional.

``` text
{prefix}devices

id
notifiable_type
notifiable_id
platform
driver
token
name
enabled
last_used_at
invalidated_at
metadata
created_at
updated_at
```

Token storage and encryption strategy must be covered by security review
before implementation.

## 4. Deliveries

Optional.

``` text
{prefix}deliveries

id
notification_id
channel
status
provider
provider_reference
attempt
queued_at
sent_at
delivered_at
failed_at
error_code
error_message
metadata
created_at
updated_at
```

Status taxonomy should be minimal and provider-neutral.

Candidate states:

``` text
pending
queued
sent
delivered
failed
skipped
```

"Delivered" must only be used when the selected provider can actually
establish that condition.

In delivery tracking, `notification_id` is the logical ID, not a foreign key
to the inbox primary key. The full tracking specification includes recipient
identity, which distinguishes deliveries for different users. Tracking can
operate without inbox persistence; an optional personal ID may be carried as
`metadata.stored_notification_id` when applicable.

## 5. Jobs

The orchestrator does not own Laravel's `jobs` / `failed_jobs` schema.

If the application selects the database queue driver, Laravel's standard
queue tables remain application infrastructure.

## 6. Migration strategy

Package migrations must:

-   resolve configured table names;
-   be publishable;
-   support fresh installation;
-   support feature-specific installation;
-   never silently rename existing user tables;
-   document migration steps for prefix changes.

Changing a table prefix after production data exists is an explicit
migration operation and must not happen automatically.

## Preference constraints

The preferences table stores only user-configurable optional channels.

The structural `database` persistence channel must not be stored as a
user preference.

A missing preference row means inheritance, not `false`.

## Delivery tracking

When the optional delivery tracking feature is enabled,
`{prefix}deliveries` stores operational delivery state.

See `DELIVERY-TRACKING.md` for the complete schema, lifecycle, privacy
and retention rules.

The delivery table is distinct from Laravel `failed_jobs` and from
persistent user notification read state.

## Persistent inbox state

`{prefix}notifications` is the authoritative recipient notification
inbox when database persistence is enabled.

Initial user state is represented only by:

``` text
read_at nullable
```

No per-device read table is used.

Recommended indexes and synchronization semantics are documented in
`PERSISTENCE-AND-SYNC.md`.
