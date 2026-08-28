# ADR-0023 --- send() returns orchestration result

Status: **Accepted**

The original field list below is historical. [ADR-0034](0034-planning-and-after-commit-execution.md)
supersedes `queuedDeliveryCount` with `plannedQueueJobCount` and defines the
immutable planning-result semantics. [ADR-0032](0032-notification-identity.md)
clarifies that `notificationId` is logical, not an inbox primary key.

## Decision

Fluent `send()` returns `NotificationDispatchResult` rather than `void`.

The result reports orchestration acceptance/planning, not final provider
delivery.

Fields:

``` text
notificationId
correlationId
recipientCount
queuedDeliveryCount
contextDeliveryCount
```

This improves traceability, logging and testing while preserving
asynchronous delivery.
