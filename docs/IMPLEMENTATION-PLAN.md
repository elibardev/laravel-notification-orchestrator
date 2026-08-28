# Implementation Plan

## Status and authority

Approved implementation organization: **three phases**, agreed on 2026-08-27.
**Phase 1 is implemented and locally verified**, including increments 1.1–1.4.
The user authorized completion after the skeleton checkpoint. Phase 2 has been
implemented and verified locally; Phase 3 was explicitly authorized on 2026-08-28
and is implemented. See [PHASE-3.md](PHASE-3.md), [PHASE-2.md](PHASE-2.md), [PHASE-1.md](PHASE-1.md) and
[TESTING.md](TESTING.md). This plan replaces the former stage lists and ten-phase
implementation order, without changing the accepted architecture or public scope.

Accepted ADRs and dedicated specifications remain authoritative. In particular:

- [ADR-0032](adr/0032-notification-identity.md): logical versus personal inbox IDs.
- [ADR-0033](adr/0033-canonical-feature-configuration.md): canonical configuration
  and feature-only activation.
- [ADR-0034](adr/0034-planning-and-after-commit-execution.md): plan once during
  `send()`, execute after commit and return an immutable planning result.

Phases are implementation boundaries, not Composer versions or single large
patches. The initial release target remains `0.1.0`; reaching a phase does not
automatically publish a release or freeze the API at 1.0.

## Execution and review rules

1. Implement only the authorized phase and its next coherent increment.
2. Before each increment, inspect current code/docs, record the baseline and run
   existing tests where available. Do not overwrite unrelated work.
3. Use contracts and fakes before provider implementations. Add tests with each
   increment, then run relevant tests, the complete available suite, static
   analysis and formatting checks before reporting its completion.
4. Preserve the mandatory skeleton checkpoint at **1.1**: stop, report results
   and obtain approval before implementing the rest of the engine.
5. At each phase boundary, report evidence and wait for explicit authorization
   before starting the next phase. Passing tests alone does not authorize it.
6. Security, configuration validation, transaction behavior and documentation
   belong to every affected increment, not only final hardening.
7. A phase is incomplete if required tests fail or its required evidence is
   unavailable. Report unavailable external verification separately; never
   describe fake-provider success as live-provider validation.
8. Material contract gaps require an accepted ADR before implementation.
   Do not silently promote proposed conveniences or future features into scope.

## Phase overview

| Phase | Outcome | Package tables introduced |
| --- | --- | --- |
| 1. Foundation and orchestration engine | Installable skeleton and deterministic planning/public fake, without real delivery providers. | None. |
| 2. Persistent inbox, clients and operations | Database-backed inbox, preferences, API, personal realtime, optional UI, queue execution and tracking. | notifications, preferences, deliveries. |
| 3. External delivery and full integration | Mail, Push/FCM, managed devices, MQTT, context transports, optional presence integration and final verification. | devices. |

Feature-specific migrations remain conditional. The default inbox installation
uses one table; enabling all managed storage modules uses four tables. This plan
does not add `dispatches`, `outbox`, `delivery_attempts` or `context_deliveries`.
Laravel queue tables are application infrastructure, not package-owned tables.

## Phase 1 — Foundation and orchestration engine

**Goal:** prove the orchestration contracts before persisting notifications or
contacting external providers.

### 1.1 Package skeleton — mandatory checkpoint

**Status (2026-08-27): complete; continuation authorized.** The six-test skeleton
baseline was reviewed before implementing 1.2–1.4. Its tests remain in the expanded
suite. No package migrations have been added.

- Configure Composer identity `elibardev/laravel-notification-orchestrator`,
  namespace, MIT license, PHP >= 8.2 and Laravel ^12.0 requirements.
- Add auto-discovery, ServiceProvider skeleton, configuration loading and the
  initial source/test/config structure. Do not prebuild unused feature modules.
- Configure Orchestra Testbench with PHPUnit or Pest, coding style, static
  analysis and CI; provide test/analyse/format scripts and a non-mutating style check.
- Add smoke tests for Laravel boot, provider registration and configuration.
- Maintain README/CHANGELOG, license and contribution/security guidance.
  Repository initialization and release tagging remain explicit repository
  operations; do not create a release merely to satisfy this checkpoint.

**Exit:** `clean + tested Laravel package skeleton`. Stop here and present
results before continuing. No notification engine or package migrations yet.

### 1.2 Configuration, identity and semantic objects

**Status: implemented and locally verified.**

- Implement centralized configuration combination/validation, CapabilityRegistry
  and TableNameResolver with prefix and per-table overrides.
- Enforce `notification-orchestrator.features.*`; preserve map/list override
  semantics, cache compatibility, disabled-provider behavior and dependency errors.
- Implement NotificationSeverity, NotificationAction, ActorReference,
  SubjectReference, NotificationContext and NotificationPayload.
- Support application-owned string/BackedEnum types, builder validation, stable
  logical/personal identity allocation and normalized payload schema 1.0.
- Implement safe normalization extension points without leaking model internals
  or assuming business-specific columns.

### 1.3 Recipient and delivery planning contracts

**Historical Phase 1 exit: implemented and verified; providers were added in Phases 2–3.**

- Implement RecipientResolver, RecipientNormalizer, RecipientFilter, composed
  sources, deduplication and exclusions; no application business rules.
- Implement ChannelKind, ChannelDefinition, RegisteredChannel, ChannelRegistry,
  health/configuration contracts and destination resolvers.
- Implement preference hierarchy with in-memory fixtures/repository substitutes;
  real preference persistence belongs to Phase 2.
- Implement ChannelPlan, SkipReason, DeliveryPlan, NotificationDispatchPlan,
  DeliveryPlanner, ChannelDelivery, DeliveryResult and executor boundaries.
  Use the accepted delivery lifecycle, not older draft status alternatives.
- Implement ContextTarget, ContextDeliveryPlan and ContextTransportRegistry
  contracts with fake transports so context assertions can already be tested.
- Introduce registry-driven `notifications:status` for implemented capabilities
  and strict/diagnostic validation. Extend it as real modules arrive; do not
  report unimplemented providers as healthy or replace them with production fakes.

### 1.4 Public API, fake and transaction boundary

**Status: implemented and locally verified, including nested transactions and fake isolation.**

- Connect NotificationBuilder, injectable NotificationOrchestrator/dispatcher,
  Notify facade, `send()` and NotificationDispatchResult.
- Implement `plannedQueueJobCount`, recipient/context counts and immutable
  snapshots. No fluent `dispatch()` alias.
- Implement Notify::fake(), RecordedNotification and all accepted assertions:
  sent/not-sent/nothing/times, recipient, planned/skipped channel and context.
- Prove planning occurs once during `send()`; defer executor/fake recording until
  the outermost commit, discard scoped work on rollback and preserve identifiers.
- Use real Testbench transaction infrastructure with fake executors/transports.
  Application test fixtures do not count as package migrations.

### Phase 1 verification and exit

Required evidence:

- Smoke tests, payload fixtures, validation/defaults and reference normalization.
- Recipient composition, deduplication, exclusions/filters, preference inheritance,
  requested-channel intersection, skip reasons, unknown/duplicate channel errors.
- Extensible registry planning with a custom fake channel and context transport.
- Logical versus personal IDs and correct counts regardless of destination count.
- Commit, rollback, nested transactions, no-transaction execution and fake isolation;
  no delivery/persistence/tracking effects during planning.
- Configuration overrides/cache behavior and diagnostics without provider secrets.
- Full available suite, style and static analysis pass.

**Exit:** the complete accepted public orchestration flow is proven with fakes.
There are still no production inbox writes or external deliveries. Report the
phase and stop for authorization.

## Phase 2 — Persistent inbox, clients and operations

**Prerequisite:** Phase 1 accepted, including its public fake and transaction tests.

**Goal:** a working inbox and client protocol over Laravel infrastructure, with
operational controls in place before adding external providers.

### 2.1 Persistence, preferences and installation

- Add conditional migrations for notifications and preferences using TableNameResolver.
- Implement the Laravel-compatible database channel/GenericNotification adapter,
  NotificationRepository, StoredNotification, NotificationQuery and pagination.
- Preserve unique personal inbox IDs and the logical ID in payload JSON.
- Implement idempotent markRead, markUnread, markAllRead and authoritative unreadCount.
- Replace in-memory preference storage with the optional repository and inheritance
  behavior; prevent structural channels from becoming user preferences.
- Implement idempotent `notifications:install`, configuration publishing and
  feature migration discovery; never overwrite user resources silently or run
  migrations from Composer.
- Test partial installations, feature activation later, prefix/override behavior
  and safe diagnostics before required migrations have been applied.

### 2.2 Queue execution and delivery tracking

- Implement one queued job per recipient/channel, stable retry identity,
  after-commit execution and database persistence before dependent deliveries.
- Test actual database-queue enqueue/worker execution with controlled fake
  channels, not only Queue::fake(). Also cover sync and queue-disabled paths.
- Add the optional deliveries migration, tracking repository, transition guard,
  destination fingerprints, provider references, retry counters and sanitized failures.
- Keep tracking writes outside planning; support tracking without inbox persistence.
- Prove provider/channel failure isolation and multiple destinations within one
  job using success/failure fakes before Phase 3 providers are introduced.

### 2.3 Personal HTTP API and broadcasting

- Implement AuthenticatedNotifiableResolver, ownership-scoped resources/queries,
  configurable middleware and route prefix/names, without requiring Sanctum.
- Implement bootstrap, listing, unread-count, read, unread and read-all endpoints.
- Add personal preference endpoints only when the feature is active.
- Implement personal broadcasting through Laravel Broadcasting and private-channel
  authorization; Reverb remains a reference deployment, not a Core dependency.
- Emit notification.created/read/unread/read_all with authoritative unread counts,
  personal inbox IDs and persistence-before-event ordering.
- Test API recovery after missed realtime events, repeated/out-of-order input,
  concurrency and unauthorized access. Context transports remain Phase 3 work.

### 2.4 Optional frontend and operations

- Build the framework-neutral JS client and Echo adapter against the implemented
  protocol; support bootstrap, deduplication, state mutations and reconnect sync.
- Add optional Blade bell, inbox and toast-container components, publishable
  views/assets, conservative scoped styles and accessible interactions.
- Keep custom Web/SPA and headless/native usage independent of Blade/package JS.
  No automatic polling or mark-read on toast display.
- Implement navigate handling and application-registered command handlers without
  granting domain authorization; verify the UI in a browser, including multiple
  clients, keyboard access, no-Echo mode and disabled Blade.
- Complete `notifications:prune` for notifications/deliveries, dry-run and scoped
  options, chunking, conservative defaults and recipient-scoped tracking cleanup.
- Extend status, structured logging, correlation and lifecycle events for real
  storage/queue/broadcast modules. No payload or secrets in default logs.

### Phase 2 verification and exit

Required evidence:

- Three conditional package migrations; install reruns preserve modified resources.
- Multi-recipient inbox/read isolation and real database queue/retry execution.
- Commit/rollback integration across inbox, tracking, queue and broadcast effects.
- API ownership, preference security, private channel authorization and API fixtures.
- Authoritative count/state synchronization, reconnect recovery and browser evidence.
- Prune dry-run/scope/default safety, tracking without inbox, redacted status/logs.
- Existing Phase 1 tests plus full available suite, style and static analysis pass.
- Database portability checks: SQLite baseline and MySQL/MariaDB/PostgreSQL
  integration evidence for combinations claimed as verified.

**Exit:** an operable persistent inbox, client protocol and optional UI, with
external channels still represented by test doubles where not yet implemented.
Do not claim live Reverb verification from a fake broadcaster. Report and stop
for authorization.

## Phase 3 — External delivery and full integration

**Prerequisite:** Phase 2 accepted, particularly persistence, execution isolation,
security, tracking and client protocol tests.

**Goal:** finish the approved optional modules and validate the package as a whole
without changing the Core contracts to accommodate individual providers.

### 3.1 Mail, Push and managed devices

- Implement Mail through Laravel's mail/notification abstractions and destination
  routing; keep SMTP vendor details outside Core.
- Implement PushDestination, PushDestinationResolver and PushDriver, first with
  fakes, then the FCM adapter and provider-neutral PushChannel.
- Add the fourth and final initial table: optional managed devices. Implement
  encrypted token storage, token_hash, installation IDs, registration/upsert,
  rotation, reassignment, logout integration guidance and invalidation.
- Implement ownership-scoped device endpoints and an external resolver mode that
  needs no managed device table.
- Verify current managed endpoint ownership/validity before sending; never send
  an earlier owner's notification to a reassigned device.
- Integrate personal ID push projections, payload minimization, failure/retry
  handling, status and tracking without treating provider acceptance as read.

### 3.2 MQTT and context transports

- Implement the MQTT driver/adapter behind the Phase 1 contracts, with Eclipse
  Mosquitto as the reference broker and configurable authorized destinations.
- Support preference-aware personal MQTT and preference-independent context MQTT.
- Implement Laravel Broadcasting context transport and the actual broadcastTo()
  and contextTo() execution paths; preserve logical payload IDs and after-commit rules.
- Verify QoS 0/1, default QoS 1/retain false, failures and safe diagnostics.
  Do not add a context-deliveries table or claim per-subscriber delivery.
- Complete custom-channel/context-transport conformance examples and tests;
  adding an extension must not require modifying Core.

### 3.3 Optional presence and operational completion

- Retain the optional presence capability already described in
  [ARCHITECTURE.md](ARCHITECTURE.md) and [REALTIME.md](REALTIME.md): application-owned
  active-context/presence integration and optional delivery policies.
- Keep presence ephemeral, disabled by default and independent of inbox/read
  state. Do not add a presence table or hard-code domain rules.
- Before implementing presence suppression, resolve any unspecified policy
  contract or skip-reason change through an ADR; the three-phase plan does not
  silently introduce a fifth SkipReason or broaden the accepted public API.
- Extend install/status for managed devices and all implemented providers;
  add configured invalidated-device pruning to the existing prune command.
- Finish provider-safe logs, lifecycle hooks and documented monitoring integration;
  optional metrics remain optional and must not require a monitoring vendor.

### 3.4 Complete-package verification and release preparation

- Run the full PHP/Laravel support matrix, schema/payload/HTTP/realtime fixtures,
  database portability tests, frontend tests and all earlier regression suites.
- Test combined features and disabled features: headless, Blade, managed/external
  push destinations, tracking with/without inbox, personal/context MQTT and failures.
- Recheck provider isolation, retry identity, encryption, token reassignment,
  redaction, destination authorization and transaction boundaries end to end.
- Keep default CI free of FCM, SMTP, Mosquitto and Reverb dependencies. Test
  adapters with controlled clients/fakes; use separate opt-in profiles for live
  integrations with approved test credentials/destinations.
- Record which real integrations were actually exercised, commands, results and
  limitations. Missing credentials are not evidence of provider verification;
  do not send to production recipients to obtain a green result.
- Check bounded recipient/destination processing, indexes and retry behavior
  against representative workloads. No new public bulk API, tables or exactly-once
  guarantee may be introduced under the label of hardening.
- Finish installation/upgrade instructions, feature examples, custom extension
  guidance, Composer metadata, license and CHANGELOG. Preparing a release does
  not authorize publishing or pushing a tag.

### Phase 3 verification and exit

**Exit:** all approved modules are implemented and covered by passing automated
tests, including the optional presence integration as specified or subsequently
clarified by an accepted ADR. Full suite, style and static analysis pass, docs
match behavior, and no secrets or unrelated changes are present.

Live-provider verification is recorded separately from automated package
verification. Any required acceptance check without evidence remains open.
A release/deployment claim may cover only the combinations actually validated.
Report residual operational limitations, including the documented after-commit
crash window; no outbox is added by this plan.

## Coverage of the former implementation order

| Previous implementation group | Current location |
| --- | --- |
| 1. Bootstrap, Composer, provider, config, Testbench, CI | Phase 1, starting with mandatory checkpoint 1.1. |
| 2. Core objects, payload, actions and builder validation | Phase 1.2. |
| 3. Recipient resolution, exclusions and filters | Phase 1.3. |
| 4. Registry, preferences and delivery planning | Phase 1.3 contracts; Phase 2.1 preference storage. |
| 5. Facade, orchestrator, result and public fake | Phase 1.4. |
| 6. Persistence, read state, API and personal broadcast | Phase 2.1–2.3. |
| 7. Blade, JS and headless adapters | Phase 2.4. |
| 8. Tracking, status, prune and observability | Initial diagnostics in Phase 1; Phase 2.2/2.4 implementation; provider/device additions in Phase 3. |
| 9. Mail, Push, Devices and FCM | Phase 3.1. |
| 10. MQTT and context transports | Phase 1.3 planning contracts/fakes; Phase 3.2 real transports. |
| Presence in the original architecture/roadmap | Phase 3.3; not silently dropped or turned into a new table. |
| Security, compatibility, performance and release guidance | Incremental checks throughout; final combination review in Phase 3.4. |

## Phase closure report

For every phase, report:

- implemented scope and changed files/classes;
- tests and quality commands actually run, with their results;
- migrations introduced and feature activation implications;
- documentation/ADR updates and any unresolved architecture decisions;
- security review, external verification evidence and limitations;
- remaining work and the next phase, awaiting authorization.

No phase is complete merely because its code has been written.
