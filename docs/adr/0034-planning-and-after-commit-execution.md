# ADR-0034 --- Plan at send time and execute after commit

Status: **Accepted**

Date: 2026-08-27

## Context

ADR-0022 requires after-commit effects and ADR-0023 requires an immediate
orchestration result with counts. Deferring recipient resolution as well as
execution would make those counts unknown at return time. The previous name
`queuedDeliveryCount` also suggested that queue acceptance had already occurred.

## Decision

### Planning at send time

`send()` synchronously validates and normalizes the notification, allocates
identifiers, resolves recipients once, deduplicates, excludes, filters,
evaluates preferences and destinations, and builds immutable recipient and
context plans. Compute the result counts from these plans before returning.

Planning may read application state but must not persist inbox/tracking rows,
enqueue work, invoke delivery providers or publish realtime effects. Application
resolvers/filters used for planning must be free of delivery side effects.

Recipient membership, preferences and destination selection reflect the moment
of `send()`. Call it after the application's relevant changes, including changes
within the current transaction. Changes made after `send()` do not silently
rebuild the plan. Snapshot normalized values and identities; do not retain
mutable model state as the definition of an immutable plan.

Resolvers are not rerun after commit or on delivery retries. Provider execution
may still fail, including for a destination that is no longer valid. A planned
destination or notification action never grants authorization.

For managed devices, execution must check that a selected endpoint still belongs
to the intended recipient and has not been disabled or invalidated. Fail safely
without contacting the provider if ownership was reassigned. This is a security
check on the planned destination, not a new recipient/preference resolution or
permission to select replacement recipients.

### Execution boundary

If a transaction is active on the application's dispatch database connection,
register execution through Laravel's after-commit infrastructure. Wait for the
outermost successful commit; a nested commit alone must not release effects.
Rollback discards plans registered in the rolled-back transaction scope.

After commit:

1. Persist recipient inbox rows when enabled, using the allocated personal IDs.
2. Persist tracking records when enabled, including planned/skipped records.
3. Enqueue or execute deliveries and publish personal/context events according
   to the plan, preserving persistence-before-dependent-delivery ordering.

Without an active transaction, execute immediately after planning. The rule
also applies when queueing is disabled or the queue connection is synchronous.
Do not require changing the application's global queue `after_commit` setting.
No public before-commit override is introduced.

The initial scope is the normal application database connection. Distributed
transactions across independent connections are not promised.

### Result contract

`NotificationDispatchResult` is an immutable planning snapshot:

``` text
notificationId
correlationId
recipientCount
plannedQueueJobCount
contextDeliveryCount
```

- `recipientCount`: final recipients after normalization, exclusions and filters.
- `plannedQueueJobCount`: recipient-delivery jobs planned for asynchronous queue
  execution; one per recipient/channel. Count a structural channel only when
  it has a separately queued job. Exclude skipped channels, synchronous
  execution and context work, which is represented by `contextDeliveryCount`.
- `contextDeliveryCount`: number of context delivery plans, not subscriber count
  and not proof of publication.

Multiple destinations within one recipient/channel job do not increase the job
count. For two recipients with queued mail and push, synchronous persistence
and no other deliveries, the count is four regardless of push device count.

Replace `queuedDeliveryCount` with `plannedQueueJobCount`; no alias is retained
for the old draft name. This explicitly supersedes that field in ADR-0023.
The result is not mutated after commit, and is not a durable receipt, delivery
status or confirmation of successful commit. A result obtained before rollback
remains only a description of a discarded plan.

### Public fake

`Notify::fake()` uses the same planning and transaction boundary. It can return
the planning result immediately but records the notification for `assertSent*`
and other dispatch assertions only when execution would occur. Before commit
there are no recorded sends; rollback records none. Real providers, inbox and
normal tracking writes remain suppressed. Do not rerun planning on commit.

## Failure and durability limits

Validation/resolver/planning errors surface during `send()`. After-commit
execution failures cannot roll back already committed business data. Report
them through the applicable execution error, logging and tracking mechanisms;
queue retries only apply once work has actually been enqueued.

After-commit callbacks do not provide atomic durability between business commit
and notification persistence/enqueue. A process crash in that interval may lose
work. A transactional outbox would require a separate accepted architecture
decision and is not introduced here. Exactly-once external delivery is not promised.

## Required tests

- Resolution occurs once during `send()` and returns deterministic plan counts.
- No inbox/tracking/queue/broadcast/context effects occur before commit.
- Commit executes the prepared plan; rollback executes and records nothing.
- Nested commits defer effects; nested rollback discards only its scoped work.
- Changes after `send()` do not change membership, preferences or result counts.
- No-transaction and synchronous-queue paths preserve the same ordering.
- Fake assertions respect commit/rollback without repeating resolvers.
- Retry identity and the multi-destination job count remain stable.
- A destination reassigned to another owner after planning never receives the
  previous owner's notification.

## Framework reference

[Laravel 12 jobs and database transactions](https://laravel.com/docs/12.x/queues#jobs-and-database-transactions)
documents after-commit dispatch and rollback behavior. The planning/result
boundary above is the package's explicit contract, not a framework guarantee
of durable handoff.
