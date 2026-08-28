# Documentation Changelog

## 2026-08-28 — Phase 2/3 implementation

- Added operational guides PHASE-2 and PHASE-3, current testing evidence and
  opt-in live-provider profiles; retained historical checkpoint evidence.
- ADR-0035 records storage/middleware/runtime refinements; ADR-0036 records
  adapters, PushMessage, managed-device ownership and optional presence policy.
- Updated current status, four-migration lifecycle and accepted presence skip reason.

## v0.2 --- 2026-08-22

Architecture decisions incorporated:

-   vendor fixed as `elibardev`;
-   package fixed as `laravel-notification-orchestrator`;
-   namespace fixed as `Elibardev\NotificationOrchestrator`;
-   MIT license accepted;
-   Laravel minimum fixed at `^12.0`;
-   PHP minimum fixed at `>= 8.2`;
-   initial queue reference is Laravel database queue;
-   Laravel Reverb is the recommended realtime implementation;
-   Laravel Broadcasting remains the abstraction boundary;
-   application database is the default database for package tables;
-   configurable table prefix retained;
-   explicit per-table overrides retained;
-   payload schema v1.0 drafted and accepted as baseline;
-   `severity` defaults to `info`;
-   `occurred_at` defaults to `now()`;
-   `actions[]` introduced for web/mobile machine-readable actions;
-   payload schema version separated from Composer package version;
-   PHP public API direction fixed as fluent API plus explicit object
    API.

## v0.3 --- 2026-08-22

-   database notification persistence declared non-user-configurable;
-   users without explicit preferences inherit defaults;
-   preference precedence defined;
-   optional channel resolution defined as system ∩ event ∩ user
    preference;
-   multiple recipient sources formally supported;
-   recipient exclusions and filters added to API design;
-   `PREFERENCES.md` specification added;
-   ADR-0008 and ADR-0009 added.

## v0.4 --- 2026-08-22

-   `broadcast` classified as structural alongside `database`;
-   structural channels declared non-user-configurable;
-   `broadcastTo()` added to the architecture as contextual Laravel
    Broadcasting;
-   MQTT added as an optional preference-aware recipient delivery
    channel;
-   Eclipse Mosquitto selected as the reference self-hosted MQTT broker;
-   MQTT explicitly separated from mobile push and contextual
    broadcasting;
-   MQTT topic convention and initial configuration model documented;
-   ADR-0010 added.

## v0.5 --- 2026-08-22

-   fail-fast configuration policy accepted for enabled channels;
-   configuration failures separated from runtime delivery failures;
-   `php artisan notifications:status` accepted as the standard
    diagnostic command;
-   HEALTHY / DEGRADED / INVALID health model defined;
-   third-party channels required to participate in diagnostics through
    contracts;
-   `CHANNELS-AND-DELIVERY.md` added;
-   `STATUS-COMMAND.md` added;
-   initial ChannelRegistry, DeliveryPlanner, DeliveryPlan,
    DestinationResolver and DeliveryResult architecture documented;
-   ADR-0011 added.

## v0.6 --- 2026-08-22

-   detailed `ChannelRegistry` design added;
-   immutable `ChannelDefinition` and `RegisteredChannel` model defined;
-   duplicate registration fails explicitly;
-   strict vs diagnostic configuration validation modes defined;
-   destination resolver and `ChannelDestination` model detailed;
-   per-recipient immutable `DeliveryPlan` defined;
-   `ChannelPlan`, `SkipReason`, `ChannelDelivery`, `DeliveryResult` and
    execution boundaries defined;
-   `NotificationDispatchPlan` introduced to combine recipient plans and
    contextual `broadcastTo()` targets;
-   structural and optional channel planning algorithms formalized;
-   default event-channel resolution documented;
-   recipient/channel queue isolation proposed;
-   ADR-0012 accepted;
-   ADR-0013 proposed.

## v0.7 --- 2026-08-22

-   ADR-0013 delivery execution isolation moved from Proposed to
    Accepted;
-   one queued delivery job per recipient/channel accepted;
-   destination-aware Delivery Tracking module designed;
-   `DELIVERY-TRACKING.md` added;
-   canonical statuses defined: planned, queued, processing, sent,
    delivered, failed, skipped;
-   delivery state explicitly separated from read/unread;
-   delivery table schema and destination privacy rules defined;
-   Laravel `failed_jobs` separation documented;
-   retry/idempotency model defined;
-   provider callback integration defined;
-   transition guard introduced;
-   default tracking scope favors optional/external channels;
-   ADR-0014 added.

## v0.8 --- 2026-08-22

-   persistent notification inbox and read/unread semantics designed;
-   database declared authoritative source for read state;
-   read state defined as recipient-level rather than device-level;
-   idempotent mark-read, mark-unread and read-all operations defined;
-   cursor-oriented inbox pagination proposed;
-   authoritative server unread counter required in relevant API
    responses and realtime events;
-   personal realtime synchronization protocol defined;
-   events added: notification.created, notification.read,
    notification.unread, notification.read_all;
-   reconnect/bootstrap recovery strategy documented;
-   push-tap read synchronization documented;
-   transaction ordering and persistence-before-broadcast invariant
    documented;
-   `PERSISTENCE-AND-SYNC.md` added;
-   ADR-0015 and ADR-0016 added.

## v0.9 --- 2026-08-22

-   Devices and Push separated architecturally;
-   package explicitly does not own users, sessions or authentication
    tokens;
-   managed and external push destination modes defined;
-   managed push tokens encrypted at rest with SHA-256 lookup
    fingerprint;
-   stable installation `device_identifier` introduced;
-   token reassignment, rotation and invalidation behavior defined;
-   PushDestinationResolver and PushDriver contracts designed;
-   MQTT reclassified as a reusable transport with recipient and
    contextual roles;
-   Context Delivery introduced as a first-class architecture layer;
-   ContextTarget, ContextDeliveryPlan, ContextTransportRegistry and
    ContextDeliveryTransport designed;
-   `broadcastTo()` retained as convenience API;
-   generalized `contextTo(ContextTarget)` proposed;
-   contextual MQTT QoS 1 / retain false defaults defined;
-   contextual delivery explicitly separated from durable notification
    delivery;
-   `DEVICES-AND-PUSH.md` and `CONTEXT-DELIVERY.md` added;
-   ADR-0017, ADR-0018 and ADR-0019 added.

## v1.0-design --- 2026-08-22

-   public fluent API fully redesigned;
-   `Notify::make()` accepted as primary ergonomic entry point;
-   explicit service API retained for advanced/injected usage;
-   `send()` selected as sole fluent terminal operation;
-   application-owned string/BackedEnum notification types defined;
-   actions, recipients, exclusions, channels and context targets
    included in builder API;
-   `broadcastTo()` retained as contextual broadcast convenience;
-   generic `contextTo(ContextTarget)` API formalized;
-   `NotificationContext`, ActorReference and SubjectReference public
    shapes proposed;
-   `NotificationDispatchResult` proposed as send() return value;
-   automatic after-commit dispatch proposed;
-   ADR-0020 and ADR-0021 accepted;
-   ADR-0022 and ADR-0023 proposed.

## v1.1-design --- 2026-08-22

-   ADR-0022 automatic after-commit moved to Accepted;
-   ADR-0023 send() orchestration result moved to Accepted;
-   installation and feature lifecycle designed;
-   `notifications:install` accepted as primary install command;
-   conditional feature registration and migration lifecycle defined;
-   first-class Blade integration accepted;
-   Blade components proposed: bell, inbox, toast container;
-   framework-neutral JS notification client defined;
-   Vue/React dependency explicitly avoided;
-   bootstrap endpoint promoted to initial API;
-   action handler registry and Blade read behavior designed;
-   reconnect and no-Echo behavior documented;
-   publishable views and minimal scoped CSS approach defined;
-   ADR-0024 and ADR-0025 added;
-   `INSTALLATION-AND-LIFECYCLE.md` and `BLADE-AND-FRONTEND.md` added.

## v1.2-design --- 2026-08-22

-   public `Notify::fake()` testing model designed and accepted;
-   logical dispatch, recipient, channel-plan and context assertions
    defined;
-   fake executes real resolution/planning while suppressing provider
    delivery;
-   `RecordedNotification` advanced inspection model introduced;
-   application consumer testing separated from package Testbench
    testing;
-   Blade explicitly classified as optional frontend adapter;
-   three frontend modes formalized: Blade, custom Web/SPA and
    headless/native;
-   API/realtime protocol declared canonical and independent of Blade;
-   package JavaScript client declared optional convenience;
-   custom authentication middleware remains application-configurable;
-   `PUBLIC-TESTING-API.md` and `HEADLESS-AND-CUSTOM-FRONTEND.md` added;
-   ADR-0026 and ADR-0027 added.

## v1.3-design --- 2026-08-22

-   security and authorization specification completed;
-   authenticated-notifiable abstraction introduced;
-   notification ownership and action authorization boundaries
    formalized;
-   MQTT, push and personal broadcast security requirements documented;
-   retention policies separated for inbox, deliveries and invalid
    devices;
-   unified `notifications:prune` command selected with dry-run support;
-   delivery retention fixed at 90 days by default when tracking is
    enabled;
-   automatic inbox pruning disabled by default;
-   provider-neutral observability model designed;
-   structured logging, correlation IDs and lifecycle event strategy
    defined;
-   metrics extension seam proposed without vendor dependency;
-   package, payload, realtime, HTTP, DB and frontend compatibility
    surfaces documented;
-   Semantic Versioning/deprecation policy defined;
-   ADR-0028 through ADR-0031 added.

## v1.4-design --- 2026-08-27

- ADR-0032 accepted: distinguish logical notification ID from personal inbox ID;
  define payload, HTTP, realtime, push and tracking identity mappings.
- Keep logical IDs in inbox JSON payloads; no dispatch table or new inbox
  column is required. Tracking is independent of inbox persistence and is not
  linked to inbox primary keys through its logical `notification_id`.
- Define recipient-scoped tracking cleanup and retry-stable personal identities.
- ADR-0033 accepted: use only `config/notification-orchestrator.php` and the
  `notification-orchestrator.*` configuration namespace.
- Make `features.<name>` the only activation switch; reject old duplicate
  module switches without aliases and never silently activate dependencies.
- Define recursive map overrides, whole-list replacement and preservation of
  valid explicit false/null/empty values, including configuration cache behavior.
- ADR-0034 accepted: validate, resolve recipients/preferences/destinations and
  build immutable plans once during `send()`; defer all persistence, tracking,
  queue and publication effects until the outermost successful commit when needed.
- Replace the draft `queuedDeliveryCount` field with `plannedQueueJobCount`;
  document planned recipient jobs separately from destinations and context work.
- Make public fake recording follow the execution boundary while retaining
  immediate planning results. Define commit, rollback and nested-transaction tests.
- Document the crash window after commit; no transactional outbox or distributed
  transaction guarantee is introduced.
- Align AGENTS, public API, persistence, configuration, tracking, client guidance
  and implementation/test specifications. Earlier changelog entries remain history.
- Documentation only: package skeleton, runtime and tests are not implemented.

## v1.5-design --- 2026-08-27

- Reorganize implementation into three phases: foundation/orchestration,
  persistent inbox/clients/operations, and external delivery/full integration.
- Replace the former implementation stage lists, ten-phase AGENTS sequence and
  eight-phase release-oriented roadmap with one consistent execution plan.
- Preserve the mandatory skeleton checkpoint as increment 1.1; require phase
  closure evidence and authorization before proceeding to the next phase.
- Define ordered increments, prerequisites, tests and completion criteria;
  contracts/fakes precede providers and security/transactions are tested throughout.
- Map all former implementation groups, including optional presence, to the new
  plan; retain the accepted four-table scope without adding future storage ideas.
- Separate automated package checks from live-provider verification, and phase
  numbering from Composer release versions or permission to publish.
- Update IMPLEMENTATION-PLAN, ROADMAP, AGENTS, README and testing guidance.
- Planning only: no implementation started and no architecture ADR is changed;
  the accepted contracts and exclusions remain in force.
