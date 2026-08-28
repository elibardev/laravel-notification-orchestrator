# ADR-0032 --- Logical notification and recipient inbox identity

Status: **Accepted**

Date: 2026-08-27

## Context

One logical dispatch may target several recipients and context transports.
Each recipient needs independent inbox/read state, while orchestration and
tracking need a shared logical identity. The earlier documents did not
distinguish these identifiers consistently.

## Decision

Use separate identities:

| Identifier | Meaning |
| --- | --- |
| `notificationId` | One logical notification per `send()`; shared across recipients and context plans. |
| `storedNotificationId` | One personal inbox notification per logical notification and recipient. |
| `correlationId` | Operational correlation; may group related operations and may default to the logical notification ID. |
| `deliveryId` | One logical tracked delivery, destination-aware where applicable; stable across retries. |

Generate logical and personal identifiers during planning. Allocate personal
identifiers only when database persistence is planned. Allocation is not
persistence: no inbox or tracking rows are written before commit.

For example, logical notification `N1` produces inbox row `A1` for Ana and
`L1` for Luis. Reading `A1` never changes `L1`.

### Representation mapping

| Surface | Identifier |
| --- | --- |
| `NotificationDispatchResult.notificationId` | Logical ID. |
| Semantic payload `id`, including context payloads | Logical ID. |
| Notifications table primary key `id` | Personal `storedNotificationId`. |
| Notifications table JSON `data.id` | Logical ID from the unchanged semantic payload. |
| Inbox HTTP resource `id`; personal `notification.created.notification.id` | Personal ID. |
| Read/unread route ID and response/event `notification_id` | Personal ID, always owner-scoped. |
| Personal push projection `notification_id` | Personal ID when persistence exists. |
| Delivery tracking `notification_id`; operational log `notification_id` | Logical ID. |

The semantic payload and personal resource/projection are different contracts.
Build projections explicitly; do not blindly merge an inbox row and payload
or change the semantic payload ID for a recipient. Read state stays outside
the semantic payload.

Inbox clients deduplicate by personal ID. Context clients deduplicate logical
occurrences by logical ID; these identifiers are not interchangeable.

### Persistence and tracking

Keep the Laravel-compatible inbox shape: one unique primary key per row and
the logical ID inside its JSON payload. No new dispatch table or additional
inbox column is required for this decision.

Tracking identity remains logical notification + recipient + channel +
destination fingerprint. Its `notification_id` is not a foreign key to the
inbox primary key. Tracking must work with database inbox persistence disabled.
If tracking metadata includes `stored_notification_id`, it denotes the
personal ID and is optional; it is not a new required column.

Pruning related tracking for one inbox row must scope by logical ID AND
recipient identity, never by logical ID alone across all recipients.

When persistence is disabled, no stored notification ID or read state is
invented. Personal push omits `notification_id` and clients must not attempt
mark-read for that projection. The semantic payload still has its logical ID.

## Consequences

- Independent read state and shared logical tracing are both explicit.
- IDs remain stable across retries; a retry must not allocate another inbox row.
- Generating an ID or returning a result does not prove persistence or delivery.
- This refines ADR-0007, ADR-0014, ADR-0015 and ADR-0023 without adding tables.
- A future indexed logical-ID column requires a separate schema decision.

## Required tests

- Two recipients share a logical ID but have distinct inbox IDs/read states.
- All personal channels for one recipient use the same personal ID where needed.
- Context payload IDs remain logical; personal HTTP/read/push IDs remain personal.
- Rollback creates no rows despite identifier allocation.
- Retries preserve inbox/delivery identity; tracking works without inbox persistence.
- Pruning one recipient's inbox does not delete another recipient's tracking.

## Framework reference

Laravel 12's default [NotificationSender](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Notifications/NotificationSender.php)
allocates recipient-specific notification IDs and its
[DatabaseChannel](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Notifications/Channels/DatabaseChannel.php)
uses the notification object's ID for the inbox row. The shared logical ID is
the orchestrator's additional semantic identity, not a replacement for that key.
